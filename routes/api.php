<?php

use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

Route::post('/articles/upload',       [ArticleController::class, 'upload']);
Route::get('/articles/search/vector',       [ArticleController::class, 'vectorSearch']);
Route::get('/articles/{id}/status',       [ArticleController::class, 'status']);

