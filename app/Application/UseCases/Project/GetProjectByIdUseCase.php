<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Domain\Entities\Project;

class GetProjectByIdUseCase
{
    protected $repository;

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $projectId): ?Project
    {
        return $this->repository->findById($projectId);
    }
}