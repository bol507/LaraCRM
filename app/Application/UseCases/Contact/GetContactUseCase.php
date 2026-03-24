<?php

namespace App\Application\UseCases\Contact;

use App\Application\Repositories\ContactRepositoryInterface;
use App\Domain\Entities\Contact;

class GetContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Get contact by ID
     * 
     * @param int $id Contact ID
     * @return Contact|null Contact found or null if not exists
     */
    public function execute(int $id): ?Contact
    {
        return $this->contactRepository->findById($id);
    }
}