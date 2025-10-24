<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TaskController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// タスク関連のAPI
Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::get('/tasks/{id}', [TaskController::class, 'show']);
Route::put('/tasks/{id}', [TaskController::class, 'update']);
Route::patch('/tasks/{id}', [TaskController::class, 'update']);
Route::post('/tasks/{id}/complete', [TaskController::class, 'complete']);
Route::post('/tasks/{id}/restore', [TaskController::class, 'restore']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
