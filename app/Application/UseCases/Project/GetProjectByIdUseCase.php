<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Domain\Entities\Project;

class GetProjectByIdUseCase
{
    

    public function __construct(
        private readonly ProjectRepositoryInterface $repository
    ){}
    
    public function execute(int $projectId): ?Project
    {   
        try{
            return $this->repository->findById($projectId);
        }
        catch(\Exception $e){
            
            return null;
        }
    }
}