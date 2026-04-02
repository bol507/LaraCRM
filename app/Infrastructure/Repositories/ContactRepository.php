<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Contact\ContactUpdateData;
use App\Application\Repositories\ContactRepositoryInterface;
use App\Domain\Entities\Contact;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for vtiger_contactdetails - Contact data table
 *
 * Handles CRUD operations for contact data in Vtiger CRM.
 * This repository implements ContactRepositoryInterface, providing both
 * simple table operations (for Create/Update UseCases) and complex
 * queries with joins (for Get/List UseCases).
 */
class ContactRepository implements ContactRepositoryInterface
{
    private const TABLE = 'vtiger_contactdetails';

    private const CONNECTION = 'vtiger';

    // =========================================================================
    // Simple CRUD Methods (used by Create/Update UseCases)
    // =========================================================================

    public function insert(array $data): int
    {
        $this->validateRequiredFields($data);

        $contactId = $data['contactid'] ?? null;
        if (! $contactId) {
            throw new InvalidArgumentException('contactid is required for insert');
        }

        DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->insert($this->prepareData($data));

        return $contactId;
    }

    public function updateContact(int $contactId, array $data): bool
    {
        if ($contactId <= 0) {
            throw new InvalidArgumentException('contactid must be positive');
        }

        if (empty($data)) {
            return true;
        }

        $sanitized = array_diff_key($data, [
            'contactid' => true,
            'contact_no' => true,
        ]);

        if (empty($sanitized)) {
            return true;
        }

        $affected = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('contactid', $contactId)
            ->update($sanitized);

        return $affected > 0;
    }

    public function findContactById(int $contactId): ?array
    {
        if ($contactId <= 0) {
            throw new InvalidArgumentException('contactid must be positive');
        }

        $row = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('contactid', $contactId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function exists(int $contactId): bool
    {
        if ($contactId <= 0) {
            return false;
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('contactid', $contactId)
            ->exists();
    }

    public function getNextContactNumber(): string
    {
        $count = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->count();

        return 'CON'.date('Y').str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // Interface Implementation: ContactRepositoryInterface
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function createWithDto(array $contactDetails, array $crmentityData): int
    {
        throw new RuntimeException('Use CreateContactUseCase for contact creation');
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $contactData, int $createdByUserId): int
    {
        throw new RuntimeException('Use CreateContactUseCase for contact creation');
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Contact
    {
        if ($id <= 0) {
            return null;
        }

        $row = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_contactdetails.accountid', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_contactdetails.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.deleted as crm_deleted',
                'vtiger_crmentity.description',
                'vtiger_account.accountname',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_users.user_name'
            )
            ->where('vtiger_contactdetails.contactid', $id)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (! $row) {
            return null;
        }

        return new Contact(
            contactid: (int) $row->contactid,
            firstname: (string) ($row->firstname ?? ''),
            lastname: (string) ($row->lastname ?? ''),
            email: (string) ($row->email ?? ''),
            phone: $row->phone ?? null,
            mobile: $row->mobile ?? null,
            title: $row->title ?? null,
            department: $row->department ?? null,
            accountid: (int) ($row->accountid ?? 0),
            account_name: $row->accountname ?? '',
            assigned_user_id: (int) ($row->smownerid ?? 0),
            assigned_user_name: $row->user_name
                ?? (($row->first_name ?? '').' '.($row->last_name ?? '')),
            description: $row->description ?? null,
            createdtime: $row->createdtime ?? null,
            modifiedtime: $row->modifiedtime ?? null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(array $filters = []): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_contactdetails.accountid', '=', 'vtiger_account.accountid')
            ->select(
                'vtiger_contactdetails.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.deleted as crm_deleted',
                'vtiger_account.accountname'
            )
            ->where('vtiger_crmentity.deleted', 0);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where('vtiger_contactdetails.firstname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_contactdetails.lastname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_contactdetails.email', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_contactdetails.phone', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($filters['accountId'])) {
            $query->where('vtiger_contactdetails.accountid', $filters['accountId']);
        }

        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 20;

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->orderBy('vtiger_contactdetails.lastname')->get();

        $contacts = $items->map(function ($row) {
            return new Contact(
                contactid: (int) $row->contactid,
                firstname: (string) ($row->firstname ?? ''),
                lastname: (string) ($row->lastname ?? ''),
                email: (string) ($row->email ?? ''),
                phone: $row->phone ?? null,
                mobile: $row->mobile ?? null,
                title: $row->title ?? null,
                department: $row->department ?? null,
                accountid: (int) ($row->accountid ?? 0),
                account_name: $row->accountname ?? '',
                assigned_user_id: (int) ($row->smownerid ?? 0),
                createdtime: $row->createdtime ?? null,
                modifiedtime: $row->modifiedtime ?? null,
            );
        });

        return new LengthAwarePaginator(
            $contacts,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function updateWithDto(ContactUpdateData $data): bool
    {
        throw new RuntimeException('Use UpdateContactUseCase for contact updates');
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, array $contactData): bool
    {
        throw new RuntimeException('Use UpdateContactUseCase for contact updates');
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Contact ID must be positive');
        }

        $updated = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $id)
            ->where('setype', 'Contacts')
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $searchTerm, ?int $accountId = null, int $limit = 10): array
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_contactdetails.accountid', '=', 'vtiger_account.accountid')
            ->select(
                'vtiger_contactdetails.contactid',
                'vtiger_contactdetails.firstname',
                'vtiger_contactdetails.lastname',
                'vtiger_contactdetails.email',
                'vtiger_contactdetails.phone',
                'vtiger_contactdetails.accountid',
                'vtiger_account.accountname'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($q) use ($searchTerm) {
                $q->where('vtiger_contactdetails.firstname', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('vtiger_contactdetails.lastname', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('vtiger_contactdetails.email', 'LIKE', "%{$searchTerm}%");
            });

        if ($accountId !== null) {
            $query->where('vtiger_contactdetails.accountid', $accountId);
        }

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function findByAccount(int $accountId, int $page = 1, int $limit = 20): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_contactdetails.accountid', '=', 'vtiger_account.accountid')
            ->select(
                'vtiger_contactdetails.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_account.accountname'
            )
            ->where('vtiger_contactdetails.accountid', $accountId)
            ->where('vtiger_crmentity.deleted', 0);

        $total = $query->count();
        $items = $query->forPage($page, $limit)->orderBy('vtiger_contactdetails.lastname')->get();

        $contacts = $items->map(function ($row) {
            return new Contact(
                contactid: (int) $row->contactid,
                firstname: (string) ($row->firstname ?? ''),
                lastname: (string) ($row->lastname ?? ''),
                email: (string) ($row->email ?? ''),
                phone: $row->phone ?? null,
                mobile: $row->mobile ?? null,
                title: $row->title ?? null,
                department: $row->department ?? null,
                accountid: (int) ($row->accountid ?? 0),
                account_name: $row->accountname ?? '',
                assigned_user_id: (int) ($row->smownerid ?? 0),
                createdtime: $row->createdtime ?? null,
                modifiedtime: $row->modifiedtime ?? null,
            );
        });

        return new LengthAwarePaginator(
            $contacts,
            $total,
            $limit,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function accountExists(int $accountId): bool
    {
        if ($accountId <= 0) {
            return false;
        }

        return DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_account.accountid', $accountId)
            ->where('vtiger_crmentity.deleted', 0)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function existsAndActive(int $contactId): bool
    {
        if ($contactId <= 0) {
            return false;
        }

        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $contactId)
            ->where('setype', 'Contacts')
            ->where('deleted', 0)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function countByClient(int $clientId): int
    {
        if ($clientId <= 0) {
            return 0;
        }

        return DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_contactdetails.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function validateRequiredFields(array $data): void
    {
        $required = ['contactid', 'lastname'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
    }

    private function prepareData(array $data): array
    {
        return [
            'contactid' => $data['contactid'],
            'contact_no' => $data['contact_no'] ?? null,
            'accountid' => $data['accountid'] ?? null,
            'salutation' => $data['salutation'] ?? null,
            'firstname' => $data['firstname'] ?? null,
            'lastname' => $data['lastname'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'title' => $data['title'] ?? null,
            'department' => $data['department'] ?? null,
            'fax' => $data['fax'] ?? null,
            'reportsto' => $data['reportsto'] ?? null,
            'training' => $data['training'] ?? null,
            'usertype' => $data['usertype'] ?? null,
            'contacttype' => $data['contacttype'] ?? 'Active',
            'otheremail' => $data['otheremail'] ?? null,
            'secondaryemail' => $data['secondaryemail'] ?? null,
            'donotcall' => $data['donotcall'] ?? '0',
            'emailoptout' => $data['emailoptout'] ?? '0',
            'imagename' => $data['imagename'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notify_owner' => $data['notify_owner'] ?? '0',
            'isconvertedfromlead' => $data['isconvertedfromlead'] ?? '0',
            'tags' => $data['tags'] ?? null,
        ];
    }
}
