<?php

namespace App\Application\UseCases\Contact;

use App\Application\DTOs\Contact\ContactDto;
use App\Application\Repositories\ContactRepositoryInterface;

class SearchContactsUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Search contacts for autocomplete
     * 
     * @param string $searchTerm Search term (minimum 2 characters)
     * @param int|null $accountId Filter by specific client
     * @return array Array of data for autocomplete
     */
    public function execute(string $searchTerm, ?int $accountId = null): array
    {
        // Validate search term
        if (strlen(trim($searchTerm)) < 2) {
            return [];
        }

        $contacts = $this->contactRepository->search(
            trim($searchTerm),
            $accountId,
            limit: 10
        );

        // Map to simplified format for autocomplete
        return array_map(
            fn($contact) => ContactDto::forSearch($contact),
            $contacts
        );
    }
}