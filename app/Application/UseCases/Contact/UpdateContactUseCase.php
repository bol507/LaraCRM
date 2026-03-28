<?php

namespace App\Application\UseCases\Contact;

use App\Application\DTOs\Contact\ContactUpdateData;
use App\Application\Repositories\ContactRepositoryInterface;
use InvalidArgumentException;

class UpdateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Update an existing contact
     * 
     * @param ContactUpdateData $data DTO with all required data
     * @return bool True if updated successfully
     * 
     * @throws InvalidArgumentException If the contact does not exist or the data is invalid
     */
    public function execute(ContactUpdateData $data): bool
    {
        // Validate that the contact exists and is active
        if (!$this->contactRepository->existsAndActive($data->contactId)) {
            throw new InvalidArgumentException('The contact does not exist or has been deleted');
        }

        // Validate data only if there are changes
        if ($data->hasChanges()) {
            $this->validateUpdateContactData($data->contactDetails, $data->contactId);
        } else {
            // If no changes, return success without doing anything
            return true;
        }

        // Execute update in the repository
        return $this->contactRepository->updateWithDto($data);
    }

    /**
     * Validate update data
     * 
     * @throws InvalidArgumentException
     */
    private function validateUpdateContactData(array $contactDetails, int $contactId): void
    {
        // lastname is the only truly required field in Vtiger
        if (isset($contactDetails['lastname']) && empty(trim($contactDetails['lastname']))) {
            throw new InvalidArgumentException('Last name cannot be empty');
        }

        // If email is provided, validate format
        if (isset($contactDetails['email']) && !empty($contactDetails['email'])) {
            if (!filter_var($contactDetails['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email');
            }
            if (strlen($contactDetails['email']) > 100) {
                throw new InvalidArgumentException('Email cannot exceed 100 characters');
            }
        }

        // If accountid is changed, validate that the account exists
        if (isset($contactDetails['accountid']) && $contactDetails['accountid'] !== null) {
            if (!is_numeric($contactDetails['accountid']) || (int) $contactDetails['accountid'] <= 0) {
                throw new InvalidArgumentException('Client ID must be a valid number');
            }
            if (!$this->contactRepository->accountExists((int) $contactDetails['accountid'])) {
                throw new InvalidArgumentException('The specified client does not exist');
            }
        }

        // Validate that contact does not report to itself
        if (isset($contactDetails['reportsto']) && (string) $contactDetails['reportsto'] === (string) $contactId) {
            throw new InvalidArgumentException('A contact cannot report to itself');
        }

        // Validate status if provided
        if (isset($contactDetails['contacttype']) && !in_array($contactDetails['contacttype'], ['Active', 'Inactive'])) {
            throw new InvalidArgumentException('Contact status must be "Active" or "Inactive"');
        }
    }
}