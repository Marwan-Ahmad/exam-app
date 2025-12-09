<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function exams(Request $request)
    {
        $query = Exam::with('user')
            ->orderByDesc('start_time');

        if ($request->filled('email')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->email . '%');
            });
        }

        if ($request->filled('finished')) {
            $finished = filter_var($request->finished, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if (!is_null($finished)) {
                $query->where('is_finished', $finished);
            }
        }

        $exams = $query->paginate(20);

        return response()->json($exams);
    }
}
