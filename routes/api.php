<?php

use App\Http\Controllers\FileController;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([VerifyApiKey::class])->group(function () {
    Route::post('/fetch-binary', [FileController::class, 'fetchBinary'])->name('fetch-binary');
    Route::post('/upload-binary', [FileController::class, 'uploadBinaryToS3'])->name('upload-binary');
});

