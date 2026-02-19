<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\QuotePDFController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\LoginController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout']);
})->middleware('jwt')
    ->group(function () {
        Route::get('/me', [LoginController::class, 'me']);
    });


Route::middleware('jwt')->group(function () {
    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/search', [UserController::class, 'searchUsers']);
    Route::get('/users/find-by-full-name', [UserController::class, 'findUserByFullName']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/profile', [UserController::class, 'updateProfile']);     
    Route::put('/users/{id}/password', [UserController::class, 'changePassword']);
    
    // User profile
    Route::get('/profile', [UserController::class, 'getMyProfile']);
    Route::put('/profile', [UserController::class, 'updateMyProfile']);

    
    
    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/search', [ClientController::class, 'search']);
    Route::get('/clients/find-by-name', [ClientController::class, 'findByAccountName']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

    

    // Opportunities
    Route::get('/opportunities', [OpportunityController::class, 'index']);
    Route::post('/opportunities', [OpportunityController::class, 'store']);
    Route::put('/opportunities/{id}', [OpportunityController::class, 'update']); 
    Route::delete('/opportunities/{id}', [OpportunityController::class, 'destroy']);

    // Quotes
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    Route::get('/quotes/{id}', [QuoteController::class, 'show']);
    Route::put('/quotes/{id}', [QuoteController::class, 'update']);
    Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']);
    Route::get('/quotes/{quoteId}/pdf/download', [QuotePDFController::class, 'generatePDF'])
        ->name('quotes.pdf.download');
    
    Route::get('/quotes/{quoteId}/pdf/preview', [QuotePDFController::class, 'previewPDF'])
        ->name('quotes.pdf.preview');
});
