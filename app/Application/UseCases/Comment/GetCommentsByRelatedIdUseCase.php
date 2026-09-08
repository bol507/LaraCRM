<?php

namespace App\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Application\ValueObjects\Comment\CommentModule;
use App\Domain\Entities\Comment;
use App\Domain\Exceptions\Comment\CommentTargetNotFoundException;
use App\Application\DTOs\Comment\CommentDto;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Get Comments By Related ID Use Case
 * 
 * Orchestrates the retrieval of comments associated with a specific record
 * (e.g., task, project, quote) in the CRM system.
 * 
 * Responsibilities:
 * - Validate input parameters (related ID, module type, pagination)
 * - Delegate data retrieval to the repository layer
 * - Transform domain entities to DTOs for presentation layer (optional)
 * - Handle pagination metadata for frontend consumption
 * 
 * This use case is part of the Application layer and should not contain:
 * - HTTP-specific logic (request/response handling)
 * - Database-specific queries (SQL, joins, etc.)
 * - UI-specific formatting (dates, localization, etc.)
 * 
 * @package App\Application\UseCases\Comment
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\Repositories\CommentRepositoryInterface
 * @see \App\Domain\Entities\Comment
 * @see \App\Application\DTOs\CommentDto
 * @see \App\Http\Controllers\Api\CommentController
 */
class GetCommentsByRelatedIdUseCase
{
   
    public function __construct(
        private readonly CommentRepositoryInterface $repository
    ){}

    /**
     * Execute the use case: retrieve comments for a related record
     * 
     * Retrieves all non-deleted comments associated with a specific record,
     * ordered by creation date (newest first) with pagination support.
     * 
     * Business rules enforced:
     * - Only returns comments where vtiger_crmentity.deleted = 0 (soft delete aware)
     * - Includes author information via join with vtiger_users table
     * - Respects visibility permissions (private vs public comments)
     * - Supports threaded comments via parent_comments field
     * 
     * @param int $relatedId ID of the related record (e.g., task ID from vtiger_activity)
     * @param string $module Module type of the related record (e.g., 'Calendar', 'Project', 'Quotes')
     * @param int $page Current page number for pagination (1-based, default: 1)
     * @param int $perPage Number of items per page (default: 50, max: 100)
     * 
     * @return LengthAwarePaginator Paginated collection of Comment entities
     * 
     * @throws InvalidArgumentException If relatedId is invalid (<= 0) or pagination params are out of range
     * @throws \RuntimeException If repository operation fails (database error, connection issue, etc.)
     * 
     * @example
     * // In a controller: get first page of comments for task #123
     * $paginator = $useCase->execute(123, 'Calendar', page: 1, perPage: 20);
     * return response()->json([
     *     'data' => $paginator->items(),
     *     'meta' => [
     *         'current_page' => $paginator->currentPage(),
     *         'total' => $paginator->total(),
     *         'last_page' => $paginator->lastPage(),
     *     ],
     * ]);
     * 
     * @example
     * // In a use case with DTO transformation for API response
     * $paginator = $this->getCommentsUseCase->execute($taskId, 'Calendar');
     * $dtos = $paginator->getCollection()->map(
     *     fn(Comment $comment) => CommentDto::fromEntity($comment)
     * );
     * return [
     *     'comments' => $dtos->all(),
     *     'pagination' => [
     *         'current_page' => $paginator->currentPage(),
     *         'per_page' => $paginator->perPage(),
     *         'total' => $paginator->total(),
     *         'last_page' => $paginator->lastPage(),
     *     ],
     * ];
     * 
     * @example
     * // Handle empty results gracefully
     * $paginator = $useCase->execute(999, 'Project'); // Non-existent task
     * if ($paginator->isEmpty()) {
     *     // Return empty response with pagination metadata
     *     return response()->json(['data' => [], 'meta' => ['total' => 0]]);
     * }
     */
    public function execute(
        int $relatedId,
        string $module,
        int $page = 1,
        int $perPage = 50
    ): LengthAwarePaginator {
        
        $this->validateParameters($relatedId, $page, $perPage);
        $this->validateRelatedRecord($relatedId, $module);
        return $this->repository->getByRelatedId($relatedId, $module, $page, $perPage);
    }

    /**
     * Validate that the module supports comments and the related record
     * exists with a matching setype.
     *
     * @param int $relatedId ID of the related record
     * @param string $module Module type of the related record
     * @return void
     *
     * @throws InvalidArgumentException If the module is not supported or the
     *                                  record belongs to another module
     * @throws CommentTargetNotFoundException If the record does not exist or is deleted
     */
    protected function validateRelatedRecord(int $relatedId, string $module): void
    {
        if (! CommentModule::isSupported($module)) {
            throw new InvalidArgumentException(
                "Module '{$module}' not allowed for comments. " .
                'Valid modules: ' . implode(', ', CommentModule::all())
            );
        }

        $recordSetype = $this->repository->relatedRecordSetype($relatedId);

        if ($recordSetype === null) {
            throw CommentTargetNotFoundException::for($module, $relatedId);
        }

        if ($recordSetype !== CommentModule::toSetype($module)) {
            throw new InvalidArgumentException(
                "Related record {$relatedId} is a {$recordSetype}, " .
                "not a {$module}. Cannot list comments for a different module."
            );
        }
    }

    /**
     * Validate input parameters for the use case
     * 
     * Performs application-level validation that is independent of
     * framework validation rules. Repository-level validation may add
     * additional checks based on database constraints.
     * 
     * @param int $relatedId ID of the related record to validate
     * @param int $page Page number to validate
     * @param int $perPage Items per page to validate
     * 
     * @return void
     * 
     * @throws InvalidArgumentException If any parameter fails validation
     */
    protected function validateParameters(int $relatedId, int $page, int $perPage): void
    {
        // Related record ID must be positive (Vtiger uses positive integers for IDs)
        if ($relatedId <= 0) {
            throw new InvalidArgumentException(
                "Related record ID must be a positive integer, got {$relatedId}"
            );
        }

        // Page number must be at least 1 (1-based pagination)
        if ($page < 1) {
            throw new InvalidArgumentException(
                "Page number must be at least 1, got {$page}"
            );
        }

        // Items per page must be within reasonable bounds
        // Too small: inefficient (many round trips)
        // Too large: performance impact (large result sets)
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException(
                "Items per page must be between 1 and 100, got {$perPage}"
            );
        }
    }

    /**
     * Transform paginated Comment entities to DTOs for API response
     * 
     * This helper method converts domain entities to data transfer objects
     * suitable for JSON serialization and frontend consumption.
     * 
     * @param LengthAwarePaginator $paginator Paginator with Comment entities
     * @return array{comments: array, pagination: array} Transformed data for API response
     * 
     * @example
     * $paginator = $this->execute($taskId, 'Calendar');
     * $response = $this->transformForApi($paginator);
     * return response()->json($response);
     */
    /*public function transformForApi(LengthAwarePaginator $paginator): array
    {
        // Map Comment entities to CommentDto instances
        $dtos = $paginator->getCollection()->map(
            fn(Comment $comment) => CommentDto::fromEntity($comment)
        );

        return [
            'comments' => $dtos->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }*/
}