<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;

class UpdateProjectUseCase
{
    protected $repository;

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $projectId, array $data): bool
    {
        return $this->repository->update($projectId, $data);
    }
}