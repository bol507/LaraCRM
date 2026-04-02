<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\CreateClientRequest;
use App\Application\DTOs\UpdateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Domain\Entities\Client;
use App\Domain\Entities\ClientSummary;
use App\Infrastructure\Mappers\ClientMapper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for vtiger_account - Client/Account data table
 *
 * Handles CRUD operations for account data in Vtiger CRM.
 * This repository implements ClientRepositoryInterface, providing both
 * simple table operations (for Create/Update UseCases) and complex
 * queries with joins (for Get/List UseCases).
 */
class AccountRepository implements ClientRepositoryInterface
{
    private const TABLE = 'vtiger_account';

    private const CONNECTION = 'vtiger';

    // =========================================================================
    // Simple CRUD Methods (used by Create/Update UseCases)
    // =========================================================================

    public function insert(array $data): int
    {
        $this->validateRequiredFields($data);

        $accountId = $data['accountid'] ?? null;
        if (! $accountId) {
            throw new InvalidArgumentException('accountid is required for insert');
        }

        DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->insert($this->prepareData($data));

        return $accountId;
    }

    public function updateAccount(int $accountId, array $data): bool
    {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('accountid must be positive');
        }

        if (empty($data)) {
            return true;
        }

        $sanitized = array_diff_key($data, [
            'accountid' => true,
            'account_no' => true,
        ]);

        if (empty($sanitized)) {
            return true;
        }

        $affected = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('accountid', $accountId)
            ->update($sanitized);

        return $affected > 0;
    }

    public function findAccountById(int $accountId): ?array
    {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('accountid must be positive');
        }

        $row = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('accountid', $accountId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function exists(int $accountId): bool
    {
        if ($accountId <= 0) {
            return false;
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('accountid', $accountId)
            ->exists();
    }

    // =========================================================================
    // Interface Implementation: ClientRepositoryInterface
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?array $filters = null
    ): LengthAwarePaginator {
        $query = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_accountbillads', 'vtiger_account.accountid', '=', 'vtiger_accountbillads.accountaddressid')
            ->leftJoin('vtiger_accountshipads', 'vtiger_account.accountid', '=', 'vtiger_accountshipads.accountaddressid')
            ->select(
                'vtiger_account.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.modifiedby',
                'vtiger_crmentity.deleted as crm_deleted',
                'vtiger_crmentity.description',
                'vtiger_accountbillads.bill_street',
                'vtiger_accountbillads.bill_city',
                'vtiger_accountbillads.bill_state',
                'vtiger_accountbillads.bill_code',
                'vtiger_accountbillads.bill_country',
                'vtiger_accountbillads.bill_pobox',
                'vtiger_accountshipads.ship_street',
                'vtiger_accountshipads.ship_city',
                'vtiger_accountshipads.ship_state',
                'vtiger_accountshipads.ship_code',
                'vtiger_accountshipads.ship_country',
                'vtiger_accountshipads.ship_pobox'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Accounts');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_account.accountname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.account_no', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.email1', 'LIKE', "%{$search}%");
            });
        }

        if ($filters) {
            foreach ($filters as $field => $value) {
                if (! empty($value)) {
                    $query->where("vtiger_account.{$field}", $value);
                }
            }
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $clients = collect(ClientMapper::fromDatabaseRows($items->all()));

        return new LengthAwarePaginator(
            $clients,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Client
    {
        if ($id <= 0) {
            return null;
        }

        $row = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_accountbillads', 'vtiger_account.accountid', '=', 'vtiger_accountbillads.accountaddressid')
            ->leftJoin('vtiger_accountshipads', 'vtiger_account.accountid', '=', 'vtiger_accountshipads.accountaddressid')
            ->select(
                'vtiger_account.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.modifiedby',
                'vtiger_crmentity.deleted as crm_deleted',
                'vtiger_crmentity.description',
                'vtiger_accountbillads.bill_street',
                'vtiger_accountbillads.bill_city',
                'vtiger_accountbillads.bill_state',
                'vtiger_accountbillads.bill_code',
                'vtiger_accountbillads.bill_country',
                'vtiger_accountbillads.bill_pobox',
                'vtiger_accountshipads.ship_street',
                'vtiger_accountshipads.ship_city',
                'vtiger_accountshipads.ship_state',
                'vtiger_accountshipads.ship_code',
                'vtiger_accountshipads.ship_country',
                'vtiger_accountshipads.ship_pobox'
            )
            ->where('vtiger_account.accountid', $id)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (! $row) {
            return null;
        }

        $clients = ClientMapper::fromDatabaseRows([$row]);

        return $clients[0] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateClientRequest $request, int $userId): int
    {
        throw new RuntimeException('Use CreateClientUseCase for client creation');
    }

    /**
     * {@inheritDoc}
     */
    public function update(UpdateClientRequest $request, int $userId): bool
    {
        throw new RuntimeException('Use UpdateClientUseCase for client updates');
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Client ID must be positive');
        }

        $updated = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $id)
            ->where('setype', 'Accounts')
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function findByAccountName(string $accountName): ?array
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_account.accountname', $accountName)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findByNameOrEmail(string $searchTerm): array
    {
        $rows = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($q) use ($searchTerm) {
                $q->where('vtiger_account.accountname', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('vtiger_account.email1', 'LIKE', "%{$searchTerm}%");
            })
            ->limit(50)
            ->get();

        return $rows->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query, int $limit): array
    {
        $rows = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Accounts')
            ->where(function ($q) use ($query) {
                $q->where('vtiger_account.accountname', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_account.account_no', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_account.email1', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $rows->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function getSummary(int $clientId): ClientSummary
    {
        if ($clientId <= 0) {
            throw new InvalidArgumentException('Client ID must be positive');
        }

        $exists = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $clientId)
            ->where('setype', 'Accounts')
            ->where('deleted', 0)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Client not found');
        }

        $opportunitiesCount = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_potential.related_to', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();

        $quotesCount = DB::connection('vtiger')
            ->table('vtiger_quotes')
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_quotes.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();

        $projectsCount = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_project.linktoaccountscontacts', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();

        $contactsCount = DB::connection('vtiger')
            ->table('vtiger_contactdetails')
            ->join('vtiger_crmentity', 'vtiger_contactdetails.contactid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_contactdetails.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();

        $lastActivity = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->whereIn('setype', ['Accounts', 'Potentials', 'Quotes', 'Project', 'Contacts'])
            ->where('crmid', $clientId)
            ->max('modifiedtime');

        return new ClientSummary(
            clientId: $clientId,
            opportunitiesCount: $opportunitiesCount,
            quotesCount: $quotesCount,
            projectsCount: $projectsCount,
            contactsCount: $contactsCount,
            lastActivity: $lastActivity
        );
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function validateRequiredFields(array $data): void
    {
        $required = ['accountid', 'accountname'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
    }

    private function prepareData(array $data): array
    {
        return [
            'accountid' => $data['accountid'],
            'account_no' => $data['account_no'] ?? null,
            'accountname' => $data['accountname'],
            'parentid' => $data['parentid'] ?? null,
            'account_type' => $data['account_type'] ?? 'Customer',
            'industry' => $data['industry'] ?? null,
            'annualrevenue' => $data['annualrevenue'] ?? null,
            'rating' => $data['rating'] ?? null,
            'ownership' => $data['ownership'] ?? null,
            'siccode' => $data['siccode'] ?? null,
            'tickersymbol' => $data['tickersymbol'] ?? null,
            'phone' => $data['phone'] ?? null,
            'otherphone' => $data['otherphone'] ?? null,
            'email1' => $data['email1'] ?? null,
            'email2' => $data['email2'] ?? null,
            'website' => $data['website'] ?? null,
            'fax' => $data['fax'] ?? null,
            'employees' => $data['employees'] ?? null,
            'emailoptout' => $data['emailoptout'] ?? '0',
            'notify_owner' => $data['notify_owner'] ?? '0',
            'isconvertedfromlead' => $data['isconvertedfromlead'] ?? '0',
            'tags' => $data['tags'] ?? null,
        ];
    }
}
