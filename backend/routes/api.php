<?php

use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::get('/movies', [RecommendationController::class, 'movies']);
Route::post('/recommend', [RecommendationController::class, 'recommend']);
