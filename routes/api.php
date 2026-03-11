<?php

use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

Route::post('/articles/upload',       [ArticleController::class, 'upload']);

