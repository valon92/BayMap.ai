<?php

use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Powerbook.ai API — Stateless, no database
|--------------------------------------------------------------------------
*/

Route::get('/health', [MetaController::class, 'health']);
Route::get('/geo', [GeoController::class, 'detect']);
Route::get('/trending', [MetaController::class, 'trending']);
Route::get('/examples', [MetaController::class, 'examples']);
Route::post('/search', [SearchController::class, 'search']);
Route::get('/search', [SearchController::class, 'search']);
