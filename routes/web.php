<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('api/v1/users')->group(function () {
    Route::get('/{cpf}', [UserController::class, 'show']);
    Route::post('/process', [UserController::class, 'process']);
});

// Mock API, to return a random status from cpf statuses //
Route::get('/cpf/status/{cpf}', function (string $cpf) {
    $statuses = ['clean', 'pending', 'negative'];
    return response()->json([
        'status' => $statuses[array_rand($statuses)]
    ]);
})->where('cpf', '[0-9]{11}');
