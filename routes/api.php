<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/exams/start', [ExamController::class, 'start']);
    Route::get('/exams/{exam}/status', [ExamController::class, 'status']);
    Route::post('/exams/{exam}/answer', [ExamController::class, 'answer']);
    Route::post('/exams/{exam}/finish', [ExamController::class, 'finish']);
    Route::get('/exams/{exam}/result', [ExamController::class, 'result']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin/exams', [AdminController::class, 'exams']);
    });
});
