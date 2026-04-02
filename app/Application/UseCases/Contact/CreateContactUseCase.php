<?php

namespace App\Application\UseCases\Contact;

use App\Application\DTOs\Contact\ContactCreateData;
use App\Application\Repositories\ContactRepositoryInterface;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Infrastructure\Repositories\ContactRepository;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Services\CurrentUserService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateContactUseCase
{
    private const ID_LOCK_NAME = 'contact_id_generation';

    private const ENTITY_SETYPE = 'Contacts';

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CreateEntityUseCase $createEntity,
        private readonly ContactRepository $contact,
    ) {}

    /**
     * Create a new contact
     *
     * Orquestación de DML:
     * 1. Generar ID único
     * 2. Insertar vtiger_crmentity (metadata)
     * 3. Insertar vtiger_contactdetails (datos del contacto)
     *
     * @param  ContactCreateData  $data  Data to create
     * @return int ID of the created contact
     *
     * @throws InvalidArgumentException If the data is invalid
     */
    public function execute(ContactCreateData $data): int
    {
        $contactDetails = $data->contactDetails;

        // Business validations
        $this->validateContactData($contactDetails);

        // Validate that the contact does not report to itself
        if (isset($contactDetails['accountid']) && $contactDetails['accountid'] !== null) {
            if (! app(ContactRepositoryInterface::class)->accountExists($contactDetails['accountid'])) {
                throw new InvalidArgumentException('El cliente especificado no existe');
            }
        }

        $userId = CurrentUserService::idOr(1);

        return DB::connection('vtiger')->transaction(function () use ($contactDetails, $userId) {
            // 1. Generar ID único
            $contactId = $this->idGenerator->generateNextId(
                table: 'vtiger_contactdetails',
                column: 'contactid',
                lockName: self::ID_LOCK_NAME
            );

            // 2. Insertar vtiger_crmentity using generic use case
            $label = trim(($contactDetails['firstname'] ?? '').' '.($contactDetails['lastname'] ?? ''));
            $this->createEntity->execute(
                data: [
                    'label' => $label,
                    'description' => $contactDetails['description'] ?? '',
                    'smownerid' => $contactDetails['assigned_user_id'] ?? $userId,
                    'smcreatorid' => $userId,
                ],
                setype: self::ENTITY_SETYPE,
                table: 'vtiger_crmentity',
                userId: $userId,
                crmId: $contactId
            );

            // 3. Insertar vtiger_contactdetails
            $this->contact->insert([
                'contactid' => $contactId,
                'contact_no' => $this->contact->getNextContactNumber(),
                'accountid' => $contactDetails['accountid'] ?? null,
                'salutation' => $contactDetails['salutation'] ?? null,
                'firstname' => $contactDetails['firstname'] ?? null,
                'lastname' => $contactDetails['lastname'],
                'email' => $contactDetails['email'] ?? null,
                'phone' => $contactDetails['phone'] ?? null,
                'mobile' => $contactDetails['mobile'] ?? null,
                'title' => $contactDetails['title'] ?? null,
                'department' => $contactDetails['department'] ?? null,
                'fax' => $contactDetails['fax'] ?? null,
                'reportsto' => $contactDetails['reportsto'] ?? null,
                'training' => $contactDetails['training'] ?? null,
                'usertype' => $contactDetails['usertype'] ?? null,
                'contacttype' => $contactDetails['contacttype'] ?? 'Active',
                'otheremail' => $contactDetails['otheremail'] ?? null,
                'secondaryemail' => $contactDetails['secondaryemail'] ?? null,
                'donotcall' => $contactDetails['donotcall'] ?? '0',
                'emailoptout' => $contactDetails['emailoptout'] ?? '0',
                'imagename' => $contactDetails['imagename'] ?? null,
                'reference' => $contactDetails['reference'] ?? null,
                'notify_owner' => $contactDetails['notify_owner'] ?? '0',
                'isconvertedfromlead' => $contactDetails['isconvertedfromlead'] ?? '0',
                'tags' => $contactDetails['tags'] ?? null,
            ]);

            return $contactId;
        });
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

        if (! empty($contactDetails['email']) && ! filter_var($contactDetails['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }

        // Validar longitudes según BD
        if (! empty($contactDetails['firstname']) && strlen($contactDetails['firstname']) > 40) {
            throw new InvalidArgumentException('Nombre no puede exceder 40 caracteres');
        }
        if (! empty($contactDetails['lastname']) && strlen($contactDetails['lastname']) > 80) {
            throw new InvalidArgumentException('Apellido no puede exceder 80 caracteres');
        }
    }
}
