<?php

namespace App\Application\UseCases\Contact;

use App\Application\DTOs\Contact\ContactCreateData;
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
     * @param ConctactCreateData $contactData Data to create
     * @return int ID of the created contact
     * 
     * @throws InvalidArgumentException If the data is invalid
     */
    public function execute(ContactCreateData $data): int
    {
        // Business validations
        $this->validateContactData($data->contactDetails);
        
        // Validate that the contact does not report to itself
        if (isset($data->contactDetails['accountid']) && $data->contactDetails['accountid'] !== null) {
            if (!$this->contactRepository->accountExists($data->contactDetails['accountid'])) {
                throw new InvalidArgumentException('El cliente especificado no existe');
            }
        }

        // Prepare data with default values
         $contactDetails = array_merge([
            'contacttype' => 'Active', 
        ], $data->contactDetails);

        // Create contact
         return $this->contactRepository->createWithDto(
            contactDetails: $contactDetails,
            crmentityData: $data->getCrmentityData()
        );

        return $contactId;
    }

    /**
     * Validate data for creating a contact
     * 
     * @throws InvalidArgumentException
     */
    private function validateContactData(array $contactDetails): void
    {
        if (empty($contactDetails['lastname'])) {
            throw new InvalidArgumentException('El apellido es requerido');
        }
        
        if (!empty($contactDetails['email']) && !filter_var($contactDetails['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }
        
        // Validar longitudes según BD
        if (!empty($contactDetails['firstname']) && strlen($contactDetails['firstname']) > 40) {
            throw new InvalidArgumentException('Nombre no puede exceder 40 caracteres');
        }
        if (!empty($contactDetails['lastname']) && strlen($contactDetails['lastname']) > 80) {
            throw new InvalidArgumentException('Apellido no puede exceder 80 caracteres');
        }
    }
}