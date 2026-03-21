<?php

namespace App\Application\UseCases\Contact;

use App\Application\Repositories\ContactRepositoryInterface;
use InvalidArgumentException;

class CreateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Create a new contact
     * 
     * @param array $contactData Contact data
     * @param int $createdByUserId ID of the authenticated user
     * @return int ID of the created contact
     * 
     * @throws InvalidArgumentException If the data is invalid
     */
    public function execute(array $contactData, int $createdByUserId): int
    {
        // Business validations
        $this->validateCreateContactData($contactData);

        // Verify that the account exists
        if (!$this->contactRepository->accountExists($contactData['accountid'])) {
            throw new InvalidArgumentException('The specified client does not exist');
        }

        // Prepare data with default values
        $contactData = array_merge([
            'contact_status' => 'Active',
            'deleted' => 0,
        ], $contactData);

        // Create contact
        return $this->contactRepository->create($contactData, $createdByUserId);
    }

    /**
     * Validate data for creating a contact
     * 
     * @throws InvalidArgumentException
     */
    private function validateCreateContactData(array $data): void
    {
        // Required fields for creation
        if (empty($data['firstname'])) {
            throw new InvalidArgumentException('First name is required');
        }
        if (empty($data['lastname'])) {
            throw new InvalidArgumentException('Last name is required');
        }
        if (empty($data['email'])) {
            throw new InvalidArgumentException('Email is required');
        }
        if (empty($data['accountid'])) {
            throw new InvalidArgumentException('Client (account) is required');
        }

        // Validate formats
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email has an invalid format');
        }

        // Validate lengths
        $this->validateStringLength($data['firstname'], 100, 'First name');
        $this->validateStringLength($data['lastname'], 100, 'Last name');
        $this->validateStringLength($data['email'], 255, 'Email');

        // Validate accountid as positive integer
        if (!is_numeric($data['accountid']) || (int) $data['accountid'] <= 0) {
            throw new InvalidArgumentException('Client ID must be a valid number');
        }

        // Validate optional fields if provided
        if (!empty($data['phone'])) {
            $this->validateStringLength($data['phone'], 50, 'Phone');
        }
        if (!empty($data['mobile'])) {
            $this->validateStringLength($data['mobile'], 50, 'Mobile');
        }
        if (!empty($data['secondaryemail']) && !filter_var($data['secondaryemail'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Secondary email has an invalid format');
        }
        if (!empty($data['birthdate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['birthdate'])) {
            throw new InvalidArgumentException('Birth date must be in YYYY-MM-DD format');
        }
        if (!empty($data['contact_status']) && !in_array($data['contact_status'], ['Active', 'Inactive'])) {
            throw new InvalidArgumentException('Contact status must be "Active" or "Inactive"');
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