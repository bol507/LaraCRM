<?php

namespace App\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCommentsByRelatedIdUseCase
{
    protected $repository;

    public function __construct(CommentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $relatedId, string $module): LengthAwarePaginator
    {
        return $this->repository->getByRelatedId($relatedId, $module);
    }
}