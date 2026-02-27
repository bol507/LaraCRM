<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Comment;
use Illuminate\Pagination\LengthAwarePaginator;

interface CommentRepositoryInterface
{
    /**
     * Get all comments with pagination
     */
    public function getByRelatedId(int $relatedId, string $module): LengthAwarePaginator;

    /**
     * get a comment by id
     */
    public function findById(int $commentId): ?Comment;

    /**
     * create a new comment
     */
    public function create(array $data): int;

    /**
     * Update a comment
     */
    public function update(int $commentId, array $data): bool;

    /**
     * Delete a comment
     */
    public function delete(int $commentId): bool;
}