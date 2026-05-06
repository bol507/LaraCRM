<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\MaterialRequestController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\QuotePDFController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoleProfileController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\VendorQuoteController;
use App\Http\Controllers\Auth\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
})->middleware('jwt')
    ->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
    });


Route::middleware('jwt')->group(function () {

    Route::prefix('users')->group(function () {

        // search
        Route::get('/search', [UserController::class, 'searchUsers']);
        Route::get('/find-by-full-name', [UserController::class, 'findUserByFullName']);



        //  Admin only
        Route::middleware('requireAdmin')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::put('/{id}/change-password', [UserController::class, 'changePassword']);
        });
    });
    // Roles
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'available']); //for roleSelect
        Route::put('/users/{userId}/role', [RoleController::class, 'assign']);
    });

    Route::prefix('settings')->middleware('requireAdmin')->group(function () {
        Route::prefix('roles')->group(function () {
            //  GET /api/settings/roles → Role[] (con counts, sharing_rule, etc.)
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
            //  GET /api/settings/roles/check-name → bool
            Route::get('/check-name', [RoleController::class, 'checkName']);
            Route::put('/{id}', [RoleController::class, 'update']);
            Route::delete('/{id}', [RoleController::class, 'destroy']);

            //  GET /api/settings/roles/{roleId} → RoleDto
            Route::put('/{roleId}/profile', [RoleProfileController::class, 'assign']);
            Route::get('/{roleId}/profile', [RoleProfileController::class, 'show']);
        });
        //  GET /api/settings/profiles → Profile[]
        Route::prefix('profiles')->group(function () {
            Route::get('/', [ProfileController::class, 'index']);
            Route::post('/', [ProfileController::class, 'store']);
            //  GET /api/settings/profiles/check-name → bool
            Route::get('/check-name', [ProfileController::class, 'checkName']);
            Route::put('/{id}', [ProfileController::class, 'update']);
            Route::get('/{id}/permissions', [ProfileController::class, 'getPermissions']);
        });
    });

    /*Route::prefix('profiles')->group(function () {
        Route::get('/', [ProfileController::class, 'index']);
        Route::post('/', [ProfileController::class, 'store']);
        Route::put('/{id}', [ProfileController::class, 'update']);
        Route::delete('/{id}', [ProfileController::class, 'destroy']);
    });*/

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
        // ===== Projects CRUD =====
        Route::get('/', [ProjectController::class, 'index']);
        Route::get('/search', [ProjectController::class, 'search']);
        Route::get('/{id}/vendors', [VendorController::class, 'byProject']);
        Route::get('/{id}', [ProjectController::class, 'show']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::put('/{id}', [ProjectController::class, 'update']);
        Route::delete('/{id}', [ProjectController::class, 'destroy']);
        // ===== Material Requests =====
        Route::prefix('{projectId}/material-requests')->group(function () {
            Route::get('/', [MaterialRequestController::class, 'index']);
            Route::get('/{requestId}', [MaterialRequestController::class, 'show']);
            Route::post('/', [MaterialRequestController::class, 'store']);
            Route::patch('/{requestId}/approve', [MaterialRequestController::class, 'approve']);
        });

        // ===== Purchase Orders =====
        Route::prefix('{projectId}/purchase-orders')->group(function () {
            // Generar POs desde ítems aprobados (tu caso actual)
            Route::post('/generate', [PurchaseOrderController::class, 'generate']);

            // CRUD estándar para gestión posterior
            Route::get('/', [PurchaseOrderController::class, 'index']);
            Route::get('/{poId}', [PurchaseOrderController::class, 'show']);
            Route::patch('/{poId}', [PurchaseOrderController::class, 'update']);
            Route::delete('/{poId}', [PurchaseOrderController::class, 'destroy']);
        });
        // ===== Vendor Quotes =====
        Route::prefix('{projectId}/vendor-quotes')->group(function () {
            Route::get('/', [VendorQuoteController::class, 'index']);
            Route::post('/', [VendorQuoteController::class, 'store']);
            Route::get('/{quoteId}', [VendorQuoteController::class, 'show']);
            Route::patch('/{quoteId}/send', [VendorQuoteController::class, 'send']);
            Route::patch('/{quoteId}/accept', [VendorQuoteController::class, 'accept']);
            Route::patch('/{quoteId}/reject', [VendorQuoteController::class, 'reject']);
        });
    });

    /*
    Route::prefix('purchase-orders')->group(function () {
        Route::post('/', [PurchaseOrderController::class, 'store']);
        Route::post('/generate-po', [PurchaseOrderController::class, 'generateFromRequests']);
        Route::get('/projects/{projectId}', [PurchaseOrderController::class, 'index']);
        Route::patch('/items/{itemId}/receive', [PurchaseOrderController::class, 'receive']);
    });
    */


    // Comment
    Route::prefix('comments')->group(function () {
        Route::get('/{commentId}', [CommentController::class, 'show']);
        Route::delete('/{commentId}', [CommentController::class, 'destroy']); // @todo: check if it works
        Route::get('/{module}/{relatedId}', [CommentController::class, 'index']);
        Route::post('/{module}/{relatedId}', [CommentController::class, 'store']);
        Route::patch('/{module}/{relatedId}/{commentId}', [CommentController::class, 'update']);
    });

    // Attachment
    Route::prefix('attachments')->group(function () {
        Route::post('/{module}/{recordId}', [AttachmentController::class, 'upload']);
        Route::get('/{module}/{recordId}', [AttachmentController::class, 'index']);
        Route::delete('/{attachmentId}', [AttachmentController::class, 'destroy']);
    });

    Route::prefix('tasks')->group(function () {
        // --- Task CRUD ---
        // Route::get('/', [TaskController::class, 'index']);                    // GET /api/tasks
        //Route::post('/', [TaskController::class, 'store']);                   // POST /api/tasks
        //Route::get('/{taskId}', [TaskController::class, 'show']);             // GET /api/tasks/{id}
        //Route::patch('/{taskId}', [TaskController::class, 'update']);         // PATCH /api/tasks/{id}
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

    Route::prefix('calendar')->group(function () {
        Route::prefix('activities')->group(function () {
            Route::get('/', [CalendarController::class, 'index']); // GET /api/calendar/activities
            Route::post('/', [CalendarController::class, 'store']);                   // POST /api/tasks
            Route::get('/{id}', [CalendarController::class, 'show']); // GET /api/calendar/activities/{id}
            Route::patch('/{id}', [CalendarController::class, 'update']); // PATCH /api/calendar/activities/{id}

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

    Route::prefix('vendors')->group(function () {
        Route::get('/', [VendorController::class, 'index']);
        Route::get('/search', [VendorController::class, 'search']); // for autocomplete
        Route::get('/{id}', [VendorController::class, 'show']);
        Route::post('/', [VendorController::class, 'store']);
        Route::put('/{id}', [VendorController::class, 'update']);
        Route::delete('/{id}', [VendorController::class, 'destroy']);
    });
});
