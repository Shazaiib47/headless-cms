<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PagePreviewController;
use App\Http\Controllers\Api\PagePublicationController;
use App\Http\Controllers\Api\PublicPageController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

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

// Published pages by slug. Excluded from Sanctum's stateful middleware so that
// anonymous visitors never start a session or receive a cookie.
Route::get('/public/pages/{slug}', PublicPageController::class)
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:120,1')
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
    ->name('public.pages.show');

// Per-email lockout lives in LoginRequest; this is a coarse per-IP backstop.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Permissions are enforced by PagePolicy (see PageController::middleware()).
    Route::apiResource('pages', PageController::class);
    Route::post('/pages/{page}/publish', [PagePublicationController::class, 'publish'])->name('pages.publish');
    Route::post('/pages/{page}/unpublish', [PagePublicationController::class, 'unpublish'])->name('pages.unpublish');
    Route::get('/pages/{page}/preview', PagePreviewController::class)->name('pages.preview');

    Route::apiResource('users', UserController::class);
});
