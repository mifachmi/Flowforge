<?php

use App\Http\Controllers\Api\AiWorkflowController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\WorkflowRunController;
use Illuminate\Support\Facades\Route;

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
});

// Protected routes
Route::middleware(['auth:api', 'tenant', 'throttle:60,1'])->group(function () {

    // Workflow CRUD — Editor & Admin
    Route::apiResource('workflows', WorkflowController::class)->middleware('role:admin,editor');

    // Rollback — Admin only
    Route::post('workflows/{id}/rollback/{version}', [WorkflowController::class, 'rollback'])
        ->middleware('role:admin');

    // Trigger manual
    Route::post('workflows/{id}/trigger', [WorkflowRunController::class, 'trigger'])
        ->middleware('role:admin,editor');

    // Run history — semua role bisa lihat
    Route::get('workflows/{id}/runs', [WorkflowRunController::class, 'index']);
    Route::get('runs/{runId}/logs', [WorkflowRunController::class, 'logs']);

    // Health panel
    Route::get('health', [WorkflowRunController::class, 'health']);

    // SSE
    Route::get('runs/{runId}/stream', [WorkflowRunController::class, 'stream'])
        ->middleware(['auth:api', 'tenant']);

    // AI Routes
    Route::post('ai/generate-workflow', [AiWorkflowController::class, 'generate'])
        ->middleware(['auth:api', 'tenant', 'throttle:10,1']); // max 10 req/menit

});
