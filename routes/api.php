<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\QuotePDFController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
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
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/search', [UserController::class, 'searchUsers']);
        Route::get('/find-by-full-name', [UserController::class, 'findUserByFullName']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::put('/{id}/password', [UserController::class, 'changePassword']);
    });

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

    // Projects
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::get('/{id}', [ProjectController::class, 'show']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::put('/{id}', [ProjectController::class, 'update']);
        Route::delete('/{id}', [ProjectController::class, 'destroy']);
    });

    // Comment Routes
    Route::prefix('comments')->group(function () {
        Route::get('/{module}/{relatedId}', [CommentController::class, 'index']);
        Route::post('/{module}/{relatedId}', [CommentController::class, 'store']);
        Route::get('/{commentId}', [CommentController::class, 'show']);
        Route::patch('/{commentId}', [CommentController::class, 'update']);
        Route::delete('/{commentId}', [CommentController::class, 'destroy']);
    });

    // Attachment Routes 
    Route::prefix('attachments')->group(function () {
        Route::post('/{module}/{recordId}', [AttachmentController::class, 'upload']);
        Route::get('/{module}/{recordId}', [AttachmentController::class, 'index']);
        Route::delete('/{attachmentId}', [AttachmentController::class, 'destroy']);
    });

    Route::prefix('tasks')->group(function () {
        // --- Task CRUD ---
        Route::get('/', [TaskController::class, 'index']);                    // GET /api/tasks
        Route::post('/', [TaskController::class, 'store']);                   // POST /api/tasks
        Route::get('/{taskId}', [TaskController::class, 'show']);             // GET /api/tasks/{id}
        Route::patch('/{taskId}', [TaskController::class, 'update']);         // PATCH /api/tasks/{id}
        Route::patch('/{taskId}/status', [TaskController::class, 'updateStatus']); // PATCH /api/tasks/{id}/status
        Route::delete('/{taskId}', [TaskController::class, 'destroy']);       // DELETE /api/tasks/{id}

        // --- Comments on Tasks (Nested) ---
        // These routes automatically receive {taskId} as parameter
        Route::prefix('{taskId}/comments')->group(function () {
            Route::get('/', [CommentController::class, 'index']);             // GET /api/tasks/{id}/comments
            Route::post('/', [CommentController::class, 'store']);            // POST /api/tasks/{id}/comments
            // Opcional: Route::patch('/{commentId}', [CommentController::class, 'updateByTask']);
            // Opcional: Route::delete('/{commentId}', [CommentController::class, 'destroyByTask']);
        });
    });

    Route::prefix('dashboard')->group(function () {
        // Tasks
        Route::get('/tasks', [DashboardController::class, 'getTasks']);
        // Update task status (mark as complete/incomplete)
        Route::patch('/tasks/{taskId}', [DashboardController::class, 'updateTaskStatus']);

        // Activity Chart
        Route::get('/activity', [DashboardController::class, 'getActivityData']);

        // Dashboard metrics
        Route::get('/metrics', [DashboardController::class, 'getMetrics']);
    });
});
