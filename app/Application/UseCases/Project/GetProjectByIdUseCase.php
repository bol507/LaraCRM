<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Domain\Entities\Project;
use Illuminate\Support\Facades\Log;

class GetProjectByIdUseCase
{
    protected $repository;

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $projectId): ?Project
    {   
        try{
            return $this->repository->findById($projectId);
        }
        catch(\Exception $e){
            Log::error('Failed to log project', [
                'projectId' => $projectId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}