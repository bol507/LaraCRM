<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Comment\GetCommentsByRelatedIdUseCase;
use App\Application\UseCases\Comment\CreateCommentUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    protected $getCommentsByRelatedIdUseCase;
    protected $createCommentUseCase;

    public function __construct(
        GetCommentsByRelatedIdUseCase $getCommentsByRelatedIdUseCase,
        CreateCommentUseCase $createCommentUseCase
    ) {
        $this->getCommentsByRelatedIdUseCase = $getCommentsByRelatedIdUseCase;
        $this->createCommentUseCase = $createCommentUseCase;
    }

    /**
     * Get all comments with pagination
     */
    public function index(Request $request, string $module, int $relatedId): JsonResponse
    {
        try {
            $comments = $this->getCommentsByRelatedIdUseCase->execute($relatedId, $module);

            return response()->json([
                'data' => $comments->items(),
                'meta' => [
                    'total' => $comments->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener comentarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new comment
     */
    public function store(Request $request, string $module, int $relatedId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'commentcontent' => 'required|string|max:5000',
                'parent_commentid' => 'nullable|integer',
                'customer' => 'nullable|integer',  
                'is_private' => 'nullable|integer',
                'filename' => 'nullable|string',
            ]);

            $authenticatedUser = $request->attributes->get('auth_user');

            $data = [
                'commentcontent' => $validated['commentcontent'],
                'related_to' => $relatedId,
                'parent_comments' => $validated['parent_commentid'] ?? null,
                'customer' => $validated['customer'] ?? null,               
                'userid' => $authenticatedUser->id,
                'is_private' => $validated['is_private'] ?? 0,
                'filename' => $validated['filename'] ?? null,
                'related_email_id' => null,
            ];

            $commentId = $this->createCommentUseCase->execute($data);

            return response()->json([
                'message' => 'Comentario creado exitosamente',
                'commentid' => $commentId
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear comentario: ' . $e->getMessage()
            ], 500);
        }
    }
}