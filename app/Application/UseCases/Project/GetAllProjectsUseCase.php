<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAllProjectsUseCase
{
    protected $repository;

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
    /**
     * Get all projects with pagination and search term filtering   
     * @param int $page
     * @param int $limit
     * @param string|null $searchTerm
     * @param string|null $status Filter by status
     * @return LengthAwarePaginator
     */
    public function execute(int $page, int $limit, ?string $searchTerm,  ?string $status = null): LengthAwarePaginator
    {
        return $this->repository->getAll($page, $limit, $searchTerm, $status);
    }
}