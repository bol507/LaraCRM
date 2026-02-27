<?php

namespace App\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;

class CreateCommentUseCase
{
    protected $repository;

    public function __construct(CommentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $data): int
    {
        return $this->repository->create($data);
    }
}