<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Project;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    /**
     * get all projects
     */
    public function getAll(int $page, int $limit, ?string $searchTerm, ?string $status): LengthAwarePaginator;

    /**
     * get a project by id
     */
    public function findById(int $projectId): ?Project;

    /**
     * Create a new project
     */
    public function create(array $data): int;

    /**
     * update a project
     */
    public function update(int $projectId, array $data): bool;

    /**
     * delete a project
     */
    public function delete(int $projectId): bool;

    /**
     * get all tasks by project
     */
    public function getTasksByProject(int $projectId): array;
}