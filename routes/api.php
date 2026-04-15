<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\QuotePDFController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\TaskController;
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
    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::post('/', [ClientController::class, 'store']);
        Route::get('/search', [ClientController::class, 'search']);
        Route::get('/find-by-name', [ClientController::class, 'findByAccountName']);
        Route::get('/{id}', [ClientController::class, 'show']);
        Route::put('/{id}', [ClientController::class, 'update']);
        Route::delete('/{id}', [ClientController::class, 'destroy']);
        Route::get('/{id}/summary', [ClientController::class, 'summary']);
    });



    // Opportunities
    Route::get('/opportunities', [OpportunityController::class, 'index']);
    Route::get('/opportunities/{id}', [OpportunityController::class, 'show']);
    Route::post('/opportunities', [OpportunityController::class, 'store']);
    Route::put('/opportunities/{id}', [OpportunityController::class, 'update']);
    Route::delete('/opportunities/{id}', [OpportunityController::class, 'destroy']);

    // Quotes
    Route::prefix('quotes')->group(function () {
        Route::get('/', [QuoteController::class, 'index']);
        Route::post('/', [QuoteController::class, 'store']);
        Route::get('/{id}', [QuoteController::class, 'show']);
        Route::put('/{id}', [QuoteController::class, 'update']);
        Route::delete('/{id}', [QuoteController::class, 'destroy']);
        Route::post('/{id}/duplicate', [QuoteController::class, 'duplicate']);
        Route::get('/{quoteId}/pdf/download', [QuotePDFController::class, 'generatePDF'])
            ->name('quotes.pdf.download');
        Route::get('/{quoteId}/pdf/preview', [QuotePDFController::class, 'previewPDF'])
            ->name('quotes.pdf.preview');
    });



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
        Route::get('/{commentId}', [CommentController::class, 'show']);
        Route::delete('/{commentId}', [CommentController::class, 'destroy']);
        Route::get('/{module}/{relatedId}', [CommentController::class, 'index']);
        Route::post('/{module}/{relatedId}', [CommentController::class, 'store']);
        Route::patch('/{module}/{relatedId}/{commentId}', [CommentController::class, 'update']);
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
            Route::get('/', [CommentController::class, 'indexByTask']);            // GET /api/tasks/{id}/comments
            Route::post('/', [CommentController::class, 'storeByTask']);            // POST /api/tasks/{id}/comments
            // Opcional: Route::patch('/{commentId}', [CommentController::class, 'updateByTask']);
            // Opcional: Route::delete('/{commentId}', [CommentController::class, 'destroyByTask']);
        });

        // --- Attachments on Tasks (Nested) ---
        Route::prefix('{taskId}/attachments')->group(function () {
            Route::get('/', [AttachmentController::class, 'indexByTask']);      // GET /api/tasks/{id}/attachments
            Route::post('/', [AttachmentController::class, 'uploadByTask']);     // POST /api/tasks/{id}/attachments
            Route::delete('/{attachmentId}', [AttachmentController::class, 'destroyByTask']); // DELETE /api/tasks/{id}/attachments/{attId}
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

    // Global search
    Route::get('/search/global', [GlobalSearchController::class, 'search']);

    // Contacts
    Route::prefix('contacts')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::get('/search', [ContactController::class, 'search']);
        Route::get('/{id}', [ContactController::class, 'show']);
        Route::post('/', [ContactController::class, 'store']);
        Route::put('/{id}', [ContactController::class, 'update']);
        Route::delete('/{id}', [ContactController::class, 'destroy']);
        Route::get('/{accountId}/contacts', [ContactController::class, 'byAccount']);
    });

    Route::prefix('activity')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });

    // Purchases
    Route::prefix('purchases')->group(function () {
        Route::get('/', [PurchaseController::class, 'index']);
        Route::post('/', [PurchaseController::class, 'store']);
        Route::get('/{id}', [PurchaseController::class, 'show']);
        Route::put('/{id}', [PurchaseController::class, 'update']);
        Route::delete('/{id}', [PurchaseController::class, 'destroy']);

        // By project
        Route::get('/project/{projectId}', [PurchaseController::class, 'byProject']);
    });
});
