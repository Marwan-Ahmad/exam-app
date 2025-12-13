<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function start(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot start exams.',
            ], 403);
        }

        if (empty($user->specialization_id)) {
            return response()->json([
                'message' => 'User does not have a specialization selected.',
            ], 422);
        }

        $existingExam = $user->exams()->latest('start_time')->first();

        if ($existingExam && !$existingExam->is_finished) {
            $exam = $existingExam;
        } elseif ($existingExam && $existingExam->is_finished) {
            return response()->json([
                'message' => 'لقد تقدمت للامتحان مسبقاً ولا يمكنك إعادة المحاولة.',
                'exam_id' => $existingExam->id,
                'final_score' => $existingExam->final_score,
            ], 403);
        } else {
            $exam = $user->exams()->create([
                'start_time' => Carbon::now(),
                'duration_seconds' => 2702,
                'category_id' => $user->specialization_id,
            ]);
        }

        $questions = Question::with('options')
            ->where('category_id', $user->specialization_id)
            ->inRandomOrder()
            ->limit(100)
            ->get();

        $responseQuestions = $questions->map(function (Question $question) {
            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => $question->options->shuffle()->map(function ($option) {
                    return [
                        'option_number' => $option->option_number,
                        'option_text' => $option->option_text,
                    ];
                })->values(),
            ];
        })->values();

        $user->load('specialization');

        return response()->json([
            'exam_id' => $exam->id,
            'start_time' => $exam->start_time,
            'duration_seconds' => $exam->duration_seconds,
            'questions' => $responseQuestions,
            'user' => [
                'name' => $user->name,
                'specialization' => $user->specialization ? ['id' => $user->specialization->id, 'name' => $user->specialization->name] : null,
            ],
            'category' => $exam->category ? ['id' => $exam->category->id, 'name' => $exam->category->name] : null,
        ], 201);
    }

    public function status(Request $request, Exam $exam)
    {
        $this->authorizeExam($request->user(), $exam);
        $this->finalizeIfExpired($exam);

        $remaining = $this->remainingSeconds($exam);

        return response()->json([
            'exam_id' => $exam->id,
            'is_finished' => $exam->is_finished,
            'remaining_seconds' => $remaining,
            'final_score' => $exam->final_score,
        ]);
    }

    public function answer(Request $request, Exam $exam)
    {
        $this->authorizeExam($request->user(), $exam);
        $this->finalizeIfExpired($exam);

        if ($exam->is_finished) {
            return response()->json([
                'message' => 'Exam is already finished.',
                'final_score' => $exam->final_score,
            ], 423);
        }

        $data = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'chosen_option' => 'required|integer|between:1,5',
        ]);

        $question = Question::with('options')->findOrFail($data['question_id']);

        $optionExists = $question->options->contains('option_number', (int) $data['chosen_option']);
        if (!$optionExists) {
            throw ValidationException::withMessages([
                'chosen_option' => ['Chosen option does not exist for this question.'],
            ]);
        }

        $isCorrect = ((int) $data['chosen_option']) === (int) $question->correct_option;

        UserAnswer::updateOrCreate(
            [
                'exam_id' => $exam->id,
                'question_id' => $question->id,
            ],
            [
                'chosen_option' => $data['chosen_option'],
                'is_correct' => $isCorrect,
            ]
        );

        return response()->json([
            'message' => 'Answer recorded.',
            'remaining_seconds' => $this->remainingSeconds($exam),
        ]);
    }

    public function finish(Request $request, Exam $exam)
    {
        // dd([
        //     'user_id_from_token' => $request->user()?->id,
        //     'exam_owner' => $exam->user_id,
        //     'token' => $request->bearerToken(),
        // ]);

        $this->authorizeExam($request->user(), $exam);
        $requiredQuestions = 100;
        $answeredCount = $exam->answers()->count();

        if (!$exam->is_finished) {
            if ($answeredCount < $requiredQuestions) {
                return response()->json([
                    'message' => 'You must answer all questions before finishing manually.',
                    'answered' => $answeredCount,
                    'remaining' => $requiredQuestions - $answeredCount,
                ], 409);
            }
            $this->finalizeExam($exam);
        }

        return response()->json([
            'message' => 'Exam finished.',
            'final_score' => $exam->final_score,
        ]);
    }

    public function result(Request $request, Exam $exam)
    {
        $this->authorizeExam($request->user(), $exam);
        $this->finalizeIfExpired($exam);

        if (!$exam->is_finished) {
            return response()->json([
                'message' => 'Exam is still in progress.',
                'remaining_seconds' => $this->remainingSeconds($exam),
            ], 409);
        }

        return response()->json([
            'exam_id' => $exam->id,
            'final_score' => $exam->final_score,
            'completed_at' => $exam->end_time,
            'user' => $exam->user ? [
                'id' => $exam->user->id,
                'name' => $exam->user->name,
                'specialization' => $exam->user->specialization ? [
                    'id' => $exam->user->specialization->id,
                    'name' => $exam->user->specialization->name,
                ] : null,
            ] : null,
        ]);
    }

    protected function authorizeExam($user, Exam $exam): void
    {
        if ((int)$exam->user_id !== $user->id) {
            abort(403, 'Unauthorized access to exam.');
        }
    }

    protected function remainingSeconds(Exam $exam): int
    {
        $elapsed = $exam->start_time->diffInSeconds(Carbon::now());
        $remaining = $exam->duration_seconds - $elapsed;

        return max(0, $remaining);
    }

    protected function finalizeIfExpired(Exam $exam): void
    {
        if ($exam->is_finished) {
            return;
        }

        if ($this->remainingSeconds($exam) <= 0) {
            $this->finalizeExam($exam);
        }
    }

    protected function finalizeExam(Exam $exam): void
    {
        if ($exam->is_finished) {
            return;
        }

        $correctCount = $exam->answers()->where('is_correct', true)->count();

        $exam->forceFill([
            'is_finished' => true,
            'end_time' => Carbon::now(),
            'final_score' => $correctCount,
        ])->save();
    }
}
