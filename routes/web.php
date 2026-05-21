<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA catch-all — Vue handles routing client-side
|--------------------------------------------------------------------------
*/

Route::view('/{any?}', 'app')->where('any', '.*');
