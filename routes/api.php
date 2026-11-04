<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\LibraryController;
use Illuminate\Support\Facades\Route;

// ---- Public ----
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'elibrary-api']));

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/guest/start', [GuestController::class, 'start'])->middleware('throttle:20,1');

Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{book}', [BookController::class, 'show']);
Route::get('/categories', [BookController::class, 'categories']);

// Download endpoints accept EITHER a Bearer token (member) OR X-Guest-Token (guest).
Route::get('/books/{book}/download', [DownloadController::class, 'download']);
Route::get('/downloads/status', [DownloadController::class, 'status']);

// ---- Authenticated members ----
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/library', [LibraryController::class, 'index']);
    Route::post('/library', [LibraryController::class, 'store']);
    Route::patch('/library/{book}/favorite', [LibraryController::class, 'toggleFavorite']);
    Route::delete('/library/{book}', [LibraryController::class, 'destroy']);
});
