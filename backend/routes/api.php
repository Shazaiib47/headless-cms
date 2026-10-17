<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = true;
    } catch (Throwable $e) {
        $database = false;
    }

    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'laravel' => app()->version(),
        'database' => $database,
    ]);
});

// Per-email lockout lives in LoginRequest; this is a coarse per-IP backstop.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Permissions are enforced by PagePolicy (see PageController::middleware()).
    Route::apiResource('pages', PageController::class);
});
