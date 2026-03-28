<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Contact\ContactUpdateData;
use App\Application\Repositories\ContactRepositoryInterface;
use App\Domain\Entities\Contact;
use App\Infrastructure\Mappers\ContactMapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VtigerContactRepository implements ContactRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function createWithDto(array $contactDetails, array $crmentityData): int
    {
        return DB::connection('vtiger')->transaction(function () use ($contactDetails, $crmentityData) {
            // Generate unique ID
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');
            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;


            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $crmid,
                    ...$crmentityData,
                ]);

            $contactDetailsData = ContactMapper::toContactDetailsArray($contactDetails, $crmid);
            DB::connection('vtiger')->table('vtiger_contactdetails')->insert([
                'contactid' => $crmid,
                ...$contactDetailsData,
            ]);

            return $crmid;
        });
    }

    /**
     * Create a new contact
     * @deprecated Use CreateWithDto() instead
     */
    public function create(array $contactData, int $createdByUserId): int
    {
        return DB::connection('vtiger')->transaction(function () use ($contactData, $createdByUserId) {
            // Generate unique ID
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');
            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;

            // Prepare mapped data
            $mappedData = ContactMapper::toPersistence($contactData, $createdByUserId);

            // 1. Insert into vtiger_crmentity
            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $crmid,
                'smownerid' => $mappedData['smownerid'],
                'smcreatorid' => $mappedData['smcreatorid'],
                'setype' => 'Contacts',
                'description' => $mappedData['description'],
                'createdtime' => $mappedData['createdtime'],
                'modifiedtime' => $mappedData['modifiedtime'],
                'deleted' => 0,
            ]);

            // 2. Insert into vtiger_contactdetails
            $contactNo = 'CON' . date('Y') . str_pad(
                DB::connection('vtiger')->table('vtiger_contactdetails')->count() + 1,
                4,
                '0',
                STR_PAD_LEFT
            );

            DB::connection('vtiger')->table('vtiger_contactdetails')->insert([
                'contactid' => $crmid,
                'contact_no' => $contactNo,
                'accountid' => $mappedData['accountid'],
                'firstname' => $mappedData['firstname'],
                'lastname' => $mappedData['lastname'],
                'email' => $mappedData['email'],
                'phone' => $mappedData['phone'],
                'mobile' => $mappedData['mobile'],
                'title' => $mappedData['title'],
                'department' => $mappedData['department'],
            ]);

            return $crmid;
        });
    }

    public function findById(int $id): ?Contact
    {
        // Query principal con JOINs para obtener datos completos
        $row = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->select(
                // Campos de vtiger_contactdetails
                'vtiger_contactdetails.contactid',
                'vtiger_contactdetails.firstname',
                'vtiger_contactdetails.lastname',
                'vtiger_contactdetails.email',
                'vtiger_contactdetails.phone',
                'vtiger_contactdetails.mobile',
                'vtiger_contactdetails.title',
                'vtiger_contactdetails.department',
                'vtiger_contactdetails.accountid',
                // Campos de vtiger_crmentity
                'vtiger_crmentity.description',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.deleted',
                'vtiger_crmentity.smownerid',
                // Datos del usuario asignado (vtiger_users)
                'vtiger_users.user_name as assigned_user_name',
                // Datos del account relacionado (vtiger_account)
                'vtiger_account.accountname'
            )
            // JOIN con crmentity para obtener metadata
            ->leftJoin('vtiger_crmentity', 'vtiger_crmentity.crmid', '=', 'vtiger_contactdetails.contactid')
            // JOIN con users para obtener nombre del usuario asignado
            ->leftJoin('vtiger_users', 'vtiger_users.id', '=', 'vtiger_crmentity.smownerid')
            // JOIN con account para obtener nombre del cliente
            ->leftJoin('vtiger_account', 'vtiger_account.accountid', '=', 'vtiger_contactdetails.accountid')
            // Filtro por ID del contacto
            ->where('vtiger_contactdetails.contactid', $id)
            // Solo contactos no eliminados
            ->where('vtiger_crmentity.deleted', 0)
            // Obtener un solo registro
            ->first();

        // Retornar null si no existe, o mapear a entidad Contact
        return $row ? ContactMapper::fromDatabaseRow($row) : null;
    }
    /**
     * List contacts with pagination and filters
     */
    public function findAll(array $filters = []): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->select(
                'vtiger_contactdetails.contactid',
                'vtiger_contactdetails.firstname',
                'vtiger_contactdetails.lastname',
                'vtiger_contactdetails.email',
                'vtiger_contactdetails.phone',
                'vtiger_contactdetails.mobile',
                'vtiger_contactdetails.title',
                'vtiger_contactdetails.department',
                'vtiger_contactdetails.accountid',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.smownerid',
                'vtiger_users.user_name as assigned_user_name',
                'vtiger_account.accountname'
            )
            ->leftJoin('vtiger_crmentity', 'vtiger_crmentity.crmid', '=', 'vtiger_contactdetails.contactid')
            ->leftJoin('vtiger_users', 'vtiger_users.id', '=', 'vtiger_crmentity.smownerid')
            ->leftJoin('vtiger_account', 'vtiger_account.accountid', '=', 'vtiger_contactdetails.accountid')
            ->where('vtiger_crmentity.deleted', 0);

        // Apply filters
        if (!empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_contactdetails.firstname', 'LIKE', $search)
                    ->orWhere('vtiger_contactdetails.lastname', 'LIKE', $search)
                    ->orWhere('vtiger_contactdetails.email', 'LIKE', $search)
                    ->orWhere('vtiger_contactdetails.phone', 'LIKE', $search)
                    ->orWhere('vtiger_contactdetails.mobile', 'LIKE', $search);
            });
        }

        if (!empty($filters['accountId'])) {
            $query->where('vtiger_contactdetails.accountid', (int) $filters['accountId']);
        }

        if (!empty($filters['assignedTo'])) {
            $query->where('vtiger_crmentity.smownerid', (int) $filters['assignedTo']);
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'lastname';
        $sortOrder = $filters['sortOrder'] ?? 'ASC';
        $validSorts = ['createdtime', 'lastname', 'email', 'firstname'];

        if (in_array($sortBy, $validSorts)) {
            $column = match ($sortBy) {
                'createdtime' => 'vtiger_crmentity.createdtime',
                'lastname' => 'vtiger_contactdetails.lastname',
                'email' => 'vtiger_contactdetails.email',
                'firstname' => 'vtiger_contactdetails.firstname',
                default => 'vtiger_contactdetails.lastname',
            };
            $query->orderBy($column, $sortOrder);
        }

        // Pagination
        return $query->paginate($filters['limit'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
    }

    /**
     * @inheritDoc
     */
    public function updateWithDto(ContactUpdateData $data): bool
    {
        return DB::connection('vtiger')->transaction(function () use ($data) {
            // Verify that the contact exists in vtiger_contactdetails
            $exists = DB::connection('vtiger')
                ->table('vtiger_contactdetails')
                ->where('contactid', $data->contactId)
                ->exists();

            if (!$exists) {
                return false;
            }

            // 1. Update vtiger_contactdetails (only changed fields)
            if (!empty($data->contactDetails)) {
                DB::connection('vtiger')
                    ->table('vtiger_contactdetails')
                    ->where('contactid', $data->contactId)
                    ->update($data->contactDetails);
            }

            // 2. Update vtiger_crmentity (always includes modifiedtime)
            $crmentityData = $data->getCrmentityData();

            if (!empty($crmentityData)) {
                DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $data->contactId)
                    ->where('setype', 'Contacts')
                    ->update($crmentityData);
            }

            return true;
        });
    }

    /**
     * Update an existing contact
     * @deprecated Use updateWithDto() instead
     */
    public function update(int $id, array $contactData): bool
    {
        return DB::connection('vtiger')->transaction(function () use ($id, $contactData) {
            // Verify that the contact exists
            $exists = DB::connection('vtiger')
                ->table('vtiger_contactdetails')
                ->where('contactid', $id)
                ->exists();

            if (!$exists) {
                return false;
            }

            // Use the mapper to get data grouped by table
            $mappedData = ContactMapper::toPersistenceUpdate(
                $contactData,
                $contactData['assigned_user_id'] ?? null
            );

            // Update vtiger_contactdetails
            if (!empty($mappedData['contactdetails'])) {
                DB::connection('vtiger')
                    ->table('vtiger_contactdetails')
                    ->where('contactid', $id)
                    ->update($mappedData['contactdetails']);
            }

            // Update vtiger_crmentity
            if (!empty($mappedData['crmentity'])) {
                DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $id)
                    ->where('setype', 'Contacts')
                    ->update($mappedData['crmentity']);
            }

            return true;
        });
    }

    /**
     * Delete a contact (soft delete)
     */
    public function delete(int $id): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $id)
            ->where('setype', 'Contacts')
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]) > 0;
    }

    /**
     * Search contacts for autocomplete
     */
    public function search(string $searchTerm, ?int $accountId = null, int $limit = 10): array
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->select(
                'vtiger_contactdetails.contactid',
                'vtiger_contactdetails.firstname',
                'vtiger_contactdetails.lastname',
                'vtiger_contactdetails.email',
                'vtiger_contactdetails.phone',
                'vtiger_contactdetails.mobile',
                'vtiger_contactdetails.title',
                'vtiger_account.accountname'
            )
            ->leftJoin('vtiger_account', 'vtiger_account.accountid', '=', 'vtiger_contactdetails.accountid')
            ->leftJoin('vtiger_crmentity', 'vtiger_crmentity.crmid', '=', 'vtiger_contactdetails.contactid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($q) use ($searchTerm) {
                $search = "%{$searchTerm}%";
                $q->where('vtiger_contactdetails.firstname', 'LIKE', $search)
                    ->orWhere('vtiger_contactdetails.lastname', 'LIKE', $search)
                    ->orWhere('vtiger_contactdetails.email', 'LIKE', $search)
                    ->orWhereRaw('CONCAT(vtiger_contactdetails.firstname, " ", vtiger_contactdetails.lastname) LIKE ?', ["%{$searchTerm}%"]);
            })
            ->limit($limit);

        if ($accountId) {
            $query->where('vtiger_contactdetails.accountid', $accountId);
        }

        $results = $query->get();

        return array_map(
            fn($row) => ContactMapper::fromDatabaseRow($row),
            $results->toArray()
        );
    }

    /**
     * Get contacts for a specific account
     */
    public function findByAccount(int $accountId, int $page = 1, int $limit = 20): LengthAwarePaginator
    {
        return $this->findAll([
            'page' => $page,
            'limit' => $limit,
            'accountId' => $accountId,
            'sortBy' => 'lastname',
            'sortOrder' => 'ASC',
        ]);
    }

    /**
     * Check if an account exists
     */
    public function accountExists(int $accountId): bool
    {
        // Verify in vtiger_crmentity where the deleted field actually exists
        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $accountId)
            ->where('setype', 'Accounts')
            ->where('deleted', 0)
            ->exists();
    }

    /**
     * Check if a contact exists and is active
     * (Same pattern for consistency)
     */
    public function existsAndActive(int $contactId): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $contactId)
            ->where('setype', 'Contacts')  // Filter by entity type
            ->where('deleted', 0)           // deleted in crmentity
            ->exists();
    }
    /**
     * @inheritDoc
     *
     * @param integer $clientId
     * @return integer
     */
    public function countByClient(int $clientId): int
    {
        return DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_contactdetails.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }
}
