<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;

class CreateProjectUseCase
{
    protected $repository;

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $data, int $createdByUserId): int
    {
        $data['created_by_user_id'] = $createdByUserId;
        if (isset($data['quoteid']) && $data['quoteid']) {
            $data['potentialid'] = $data['quoteid']; 
        }
        if (!isset($data['assigned_user_id'])) {
            $data['assigned_user_id'] = null;
        }
        return $this->repository->create($data);
    }
}
