<?php

namespace App\Application\UseCases\Contact;

use App\Application\Repositories\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListContactsUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * List contacts with pagination and filters
     * 
     * @param array $filters Search filters
     * @return LengthAwarePaginator
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        // Validate and sanitize filters
        $filters = $this->sanitizeFilters($filters);

        return $this->contactRepository->findAll($filters);
    }

    /**
     * Sanitize and validate input filters
     */
    private function sanitizeFilters(array $filters): array
    {
        return [
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($filters['limit'] ?? 20))),
            'search' => isset($filters['search']) ? trim($filters['search']) : null,
            'accountId' => isset($filters['accountId']) ? (int) $filters['accountId'] : null,
            'assignedTo' => isset($filters['assignedTo']) ? (int) $filters['assignedTo'] : null,
            'status' => in_array($filters['status'] ?? '', ['Active', 'Inactive']) 
                ? $filters['status'] 
                : null,
            'sortBy' => in_array($filters['sortBy'] ?? '', ['createdtime', 'lastname', 'email', 'firstname']) 
                ? $filters['sortBy'] 
                : 'lastname',
            'sortOrder' => strtoupper($filters['sortOrder'] ?? '') === 'ASC' ? 'ASC' : 'DESC',
        ];
    }
}