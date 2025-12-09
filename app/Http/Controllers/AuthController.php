<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|between:3,15',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $code = $this->createVerificationCode($user);
        $this->sendVerificationEmail($user, $code);

        return response()->json([
            'message' => 'Account created. Verification code sent to email.',
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user->verification_code || $user->verification_code !== $data['code']) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        if ($user->verification_code_expires_at && Carbon::now()->greaterThan($user->verification_code_expires_at)) {
            throw ValidationException::withMessages([
                'code' => ['Verification code has expired.'],
            ]);
        }

        $user->forceFill([
            'email_verified_at' => Carbon::now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (is_null($user->email_verified_at)) {
            $code = $this->createVerificationCode($user);
            $this->sendVerificationEmail($user, $code);

            return response()->json([
                'message' => 'Email not verified. Verification code resent.',
            ], 403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    protected function createVerificationCode(User $user): string
    {
        $code = (string) rand(100000, 999999);

        $user->forceFill([
            'verification_code' => $code,
            'verification_code_expires_at' => Carbon::now()->addMinutes(15),
            'email_verified_at' => null,
        ])->save();

        return $code;
    }

    protected function sendVerificationEmail(User $user, string $code): void
    {
        Mail::raw("Your verification code is: {$code}", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Email Verification Code');
        });
    }
}
