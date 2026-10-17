<?php

use App\Http\Controllers\Api\PageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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

// TEMPORARILY UNAUTHENTICATED: Milestone 06 wraps these routes in
// auth:sanctum plus policies. Do not deploy before then.
Route::apiResource('pages', PageController::class);
