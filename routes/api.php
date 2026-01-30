<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Rutas PÚBLICAS (sin autenticación)
Route::prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout']);
    

})->middleware('jwt') 
    ->group(function () {
        Route::get('/me', [LoginController::class, 'me']);
    });

    
// Otras rutas protegidas de tu CRM
Route::middleware('jwt')->group(function () {
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::post('/clients', [ClientController::class, 'store']);
});