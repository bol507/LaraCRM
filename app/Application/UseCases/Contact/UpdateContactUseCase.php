<?php

namespace App\Application\UseCases\Contact;

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
     * @param int $id ID of the contact to update
     * @param array $contactData Data to update
     * @return bool True if updated successfully
     * 
     * @throws InvalidArgumentException If the contact does not exist or the data is invalid
     */
    public function execute(int $id, array $contactData): bool
    {
        // Validate that the contact exists and is active
        if (!$this->contactRepository->existsAndActive($id)) {
            throw new InvalidArgumentException('The contact does not exist or has been deleted');
        }

        // Validate input data
        $this->validateUpdateContactData($contactData, $id);

        // Prepare data with default values
        $contactData = array_merge([
            'modifiedtime' => now()->format('Y-m-d H:i:s'),
        ], $contactData);

        // Update contact
        return $this->contactRepository->update($id, $contactData);
    }

    /**
     * Validate data for updating a contact
     * 
     * @throws InvalidArgumentException
     */
    private function validateUpdateContactData(array $data, int $contactId): void
    {
        // Only validate fields that are provided (partial update)
        if (isset($data['firstname']) && empty(trim($data['firstname']))) {
            throw new InvalidArgumentException('First name cannot be empty');
        }
        if (isset($data['lastname']) && empty(trim($data['lastname']))) {
            throw new InvalidArgumentException('Last name cannot be empty');
        }
        if (isset($data['email']) && !empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Email has an invalid format');
            }
            $this->validateStringLength($data['email'], 255, 'Email');
        }

        // If account is being changed, validate it exists
        if (isset($data['accountid']) && !empty($data['accountid'])) {
            if (!is_numeric($data['accountid']) || (int) $data['accountid'] <= 0) {
                throw new InvalidArgumentException('Client ID must be a valid number');
            }
            if (!$this->contactRepository->accountExists((int) $data['accountid'])) {
                throw new InvalidArgumentException('The specified client does not exist');
            }
        }

        // Validate lengths for optional fields
        if (isset($data['firstname'])) {
            $this->validateStringLength($data['firstname'], 100, 'First name');
        }
        if (isset($data['lastname'])) {
            $this->validateStringLength($data['lastname'], 100, 'Last name');
        }

        // Validate that contact does not report to itself
        if (isset($data['reports_to_id']) && (int) $data['reports_to_id'] === $contactId) {
            throw new InvalidArgumentException('A contact cannot report to itself');
        }

        // Validate status if provided
        if (isset($data['contact_status']) && !in_array($data['contact_status'], ['Active', 'Inactive'])) {
            throw new InvalidArgumentException('Contact status must be "Active" or "Inactive"');
        }

        // Validate birth date if provided
        if (isset($data['birthdate']) && !empty($data['birthdate'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['birthdate'])) {
                throw new InvalidArgumentException('Birth date must be in YYYY-MM-DD format');
            }
        }
    }

    /**
     * Helper to validate string length
     */
    private function validateStringLength(?string $value, int $max, string $fieldName): void
    {
        if ($value !== null && strlen($value) > $max) {
            throw new InvalidArgumentException("{$fieldName} cannot exceed {$max} characters");
        }
    }
}