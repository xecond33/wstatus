<?php

use App\Http\Controllers\EncodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EncodeController::class, 'index'])->name('home');

Route::post('/encode', [EncodeController::class, 'store'])
    ->middleware('throttle:encode')
    ->name('encode.store');

Route::get('/encode/{job}', [EncodeController::class, 'show'])
    ->name('encode.show');

Route::get('/encode/{job}/download/{index}', [EncodeController::class, 'download'])
    ->middleware('signed')
    ->name('encode.download');
