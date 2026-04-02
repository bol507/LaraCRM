<?php

namespace App\Application\UseCases\Contact;

use App\Application\DTOs\Contact\ContactUpdateData;
use App\Application\Repositories\ContactRepositoryInterface;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\ContactRepository;
use App\Services\CurrentUserService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly UpdateEntityUseCase $updateEntity,
        private readonly ContactRepository $contact,
    ) {}

    /**
     * Update an existing contact
     *
     * Orquestación de DML:
     * 1. Validar que el contacto existe
     * 2. Validar datos
     * 3. Actualizar vtiger_contactdetails (datos del contacto)
     * 4. Actualizar vtiger_crmentity (label + modifiedby)
     *
     * @param  ContactUpdateData  $data  DTO with all required data
     * @param  int|null  $userId  User performing the update
     * @return bool True if updated successfully
     *
     * @throws InvalidArgumentException If the contact does not exist or the data is invalid
     */
    public function execute(ContactUpdateData $data, ?int $userId = null): bool
    {
        // Validate that the contact exists and is active
        if (! $this->contactRepository->existsAndActive($data->contactId)) {
            throw new InvalidArgumentException('The contact does not exist or has been deleted');
        }

        $userId = $userId ?? CurrentUserService::idOr(1);

        // Validate data only if there are changes
        if ($data->hasChanges()) {
            $this->validateUpdateContactData($data->contactDetails, $data->contactId);
        } else {
            return true;
        }

        // Actualizar en transacción
        DB::connection('vtiger')->transaction(function () use ($data, $userId) {
            // 1. Actualizar vtiger_contactdetails
            if (! empty($data->contactDetails)) {
                $this->contact->updateContact($data->contactId, $data->contactDetails);
            }

            // 2. Actualizar vtiger_crmentity using generic use case
            $label = trim(
                ($data->contactDetails['firstname'] ?? '').' '.
                ($data->contactDetails['lastname'] ?? '')
            );

            $this->updateEntity->execute(
                crmId: $data->contactId,
                data: ['label' => $label],
                userId: $userId
            );
        });

        return true;
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
        if (isset($contactDetails['email']) && ! empty($contactDetails['email'])) {
            if (! filter_var($contactDetails['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email');
            }
            if (strlen($contactDetails['email']) > 100) {
                throw new InvalidArgumentException('Email cannot exceed 100 characters');
            }
        }

        // If accountid is changed, validate that the account exists
        if (isset($contactDetails['accountid']) && $contactDetails['accountid'] !== null) {
            if (! is_numeric($contactDetails['accountid']) || (int) $contactDetails['accountid'] <= 0) {
                throw new InvalidArgumentException('Client ID must be a valid number');
            }
            if (! $this->contactRepository->accountExists((int) $contactDetails['accountid'])) {
                throw new InvalidArgumentException('The specified client does not exist');
            }
        }

        // Validate that contact does not report to itself
        if (isset($contactDetails['reportsto']) && (string) $contactDetails['reportsto'] === (string) $contactId) {
            throw new InvalidArgumentException('A contact cannot report to itself');
        }

        // Validate status if provided
        if (isset($contactDetails['contacttype']) && ! in_array($contactDetails['contacttype'], ['Active', 'Inactive'])) {
            throw new InvalidArgumentException('Contact status must be "Active" or "Inactive"');
        }
    }
}
