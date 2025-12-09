<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'adminadmin@gmail.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('adminadmin'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'verification_code' => null,
                'verification_code_expires_at' => null,
                'remember_token' => Str::random(10),
            ]
        );
    }
}
