<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\ContactRepositoryInterface;
use App\Domain\Entities\Contact;
use App\Infrastructure\Mappers\ContactMapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VtigerContactRepository implements ContactRepositoryInterface
{

    

    /**
     * Create a new contact
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
            DB::connection('vtiger')->table('vtiger_contactdetails')->insert([
                'contactid' => $crmid,
                'accountid' => $mappedData['accountid'],
                'firstname' => $mappedData['firstname'],
                'lastname' => $mappedData['lastname'],
                'email' => $mappedData['email'],
                'phone' => $mappedData['phone'],
                'mobile' => $mappedData['mobile'],
                'title' => $mappedData['title'],
                'department' => $mappedData['department'],
                'mailingstreet' => $mappedData['mailingstreet'],
                'mailingcity' => $mappedData['mailingcity'],
                'mailingstate' => $mappedData['mailingstate'],
                'mailingcountry' => $mappedData['mailingcountry'],
                'mailingzip' => $mappedData['mailingzip'],
                'otherphone' => $mappedData['otherphone'],
                'fax' => $mappedData['fax'],
                'secondaryemail' => $mappedData['secondaryemail'],
                'assistant' => $mappedData['assistant'],
                'birthdate' => $mappedData['birthdate'],
                'reports_to_id' => $mappedData['reports_to_id'],
                'leadsource' => $mappedData['leadsource'],
                'contact_status' => $mappedData['contact_status'],
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
                'vtiger_contactdetails.mailingstreet',
                'vtiger_contactdetails.mailingcity',
                'vtiger_contactdetails.mailingstate',
                'vtiger_contactdetails.mailingcountry',
                'vtiger_contactdetails.mailingzip',
                'vtiger_contactdetails.otherphone',
                'vtiger_contactdetails.fax',
                'vtiger_contactdetails.secondaryemail',
                'vtiger_contactdetails.assistant',
                'vtiger_contactdetails.birthdate',
                'vtiger_contactdetails.reports_to_id',
                'vtiger_contactdetails.leadsource',
                'vtiger_contactdetails.contact_status',
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
                'vtiger_contactdetails.contact_status',
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

        if (!empty($filters['status']) && in_array($filters['status'], ['Active', 'Inactive'])) {
            $query->where('vtiger_contactdetails.contact_status', $filters['status']);
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
     * Update an existing contact
     */
    public function update(int $id, array $contactData): bool
    {
        return DB::connection('vtiger')->transaction(function () use ($id, $contactData) {
            // Verify that it exists
            $exists = DB::connection('vtiger')
                ->table('vtiger_contactdetails')
                ->where('contactid', $id)
                ->exists();

            if (!$exists) {
                return false;
            }

            // Update vtiger_contactdetails
            DB::connection('vtiger')
                ->table('vtiger_contactdetails')
                ->where('contactid', $id)
                ->update([
                    'firstname' => $contactData['firstname'] ?? null,
                    'lastname' => $contactData['lastname'] ?? null,
                    'email' => $contactData['email'] ?? null,
                    'phone' => $contactData['phone'] ?? null,
                    'mobile' => $contactData['mobile'] ?? null,
                    'title' => $contactData['title'] ?? null,
                    'department' => $contactData['department'] ?? null,
                    'accountid' => $contactData['accountid'] ?? null,
                    'mailingstreet' => $contactData['mailingstreet'] ?? null,
                    'mailingcity' => $contactData['mailingcity'] ?? null,
                    'mailingstate' => $contactData['mailingstate'] ?? null,
                    'mailingcountry' => $contactData['mailingcountry'] ?? null,
                    'mailingzip' => $contactData['mailingzip'] ?? null,
                    'otherphone' => $contactData['otherphone'] ?? null,
                    'fax' => $contactData['fax'] ?? null,
                    'secondaryemail' => $contactData['secondaryemail'] ?? null,
                    'assistant' => $contactData['assistant'] ?? null,
                    'birthdate' => $contactData['birthdate'] ?? null,
                    'reports_to_id' => $contactData['reports_to_id'] ?? null,
                    'leadsource' => $contactData['leadsource'] ?? null,
                    'contact_status' => $contactData['contact_status'] ?? null,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            // Update description in crmentity if provided
            if (isset($contactData['description'])) {
                DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $id)
                    ->update([
                        'description' => $contactData['description'],
                        'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    ]);
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
        return DB::connection('vtiger')
            ->table('vtiger_account')
            ->where('accountid', $accountId)
            ->where('deleted', 0)
            ->exists();
    }

    /**
     * Check if a contact exists and is active
     */
    public function existsAndActive(int $contactId): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_crmentity.crmid', '=', 'vtiger_contactdetails.contactid')
            ->where('vtiger_contactdetails.contactid', $contactId)
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_contactdetails.contact_status', 'Active')
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
