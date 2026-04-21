<?php

namespace App\Application\UseCases\Project;

use App\Application\Repositories\ProjectRepositoryInterface;
use InvalidArgumentException;

class SearchProjectsUseCase
{
    public function __construct(
        private ProjectRepositoryInterface $repository,
    ) {}

    /**
     * Execute the search projects use case.
     *
     * @param string $term Search term
     * @param int $limit Maximum results (default: 10)
     * @return array Array of project search results
     *
     * @throws InvalidArgumentException If term is too short
     */
    public function execute(string $term, int $limit = 10): array
    {
        // 1. Validate input
        $this->validateParameters($term);

        // 2. Delegate to repository
        return $this->repository->search(trim($term), $limit);
    }

    /**
     * Validate input parameters.
     *
     * @param string $term Search term
     * @throws InvalidArgumentException If term is invalid
     */
    private function validateParameters(string $term): void
    {
        if (strlen(trim($term)) < 2) {
            throw new InvalidArgumentException('Search term must be at least 2 characters');
        }
    }
}