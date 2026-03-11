<?php

use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

// Public API endpoints. Add auth middleware here when the client auth flow is ready.
Route::middleware('throttle:30,1')->group(function () {
	Route::post('/articles/upload', [ArticleController::class, 'upload']);
	Route::get('/articles/search/vector', [ArticleController::class, 'vectorSearch']);
	Route::get('/articles/{id}/status', [ArticleController::class, 'status']);
	Route::get('/articles/{id}/chunks', [ArticleController::class, 'checkChunks']);
});

