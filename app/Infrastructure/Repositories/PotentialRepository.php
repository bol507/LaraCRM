<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\CreateOpportunityRequest;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Domain\Entities\Opportunity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for vtiger_potential - Opportunity/Potential data table
 *
 * Handles CRUD operations for opportunity data in Vtiger CRM.
 * This repository implements OpportunityRepositoryInterface, providing both
 * simple table operations (for Create/Update UseCases) and complex
 * queries with joins (for Get/List UseCases).
 */
class PotentialRepository implements OpportunityRepositoryInterface
{
    private const TABLE = 'vtiger_potential';

    private const CONNECTION = 'vtiger';

    // =========================================================================
    // Simple CRUD Methods (used by Create/Update UseCases)
    // =========================================================================

    public function insert(array $data): int
    {
        $this->validateRequiredFields($data);

        $potentialId = $data['potentialid'] ?? null;
        if (! $potentialId) {
            throw new InvalidArgumentException('potentialid is required for insert');
        }

        DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->insert($this->prepareData($data));

        return $potentialId;
    }

    public function updatePotential(int $potentialId, array $data): bool
    {
        if ($potentialId <= 0) {
            throw new InvalidArgumentException('potentialid must be positive');
        }

        if (empty($data)) {
            return true;
        }

        $sanitized = array_diff_key($data, [
            'potentialid' => true,
            'potential_no' => true,
        ]);

        if (empty($sanitized)) {
            return true;
        }

        $affected = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('potentialid', $potentialId)
            ->update($sanitized);

        return $affected > 0;
    }

    public function findPotentialById(int $potentialId): ?array
    {
        if ($potentialId <= 0) {
            throw new InvalidArgumentException('potentialid must be positive');
        }

        $row = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('potentialid', $potentialId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function exists(int $potentialId): bool
    {
        if ($potentialId <= 0) {
            return false;
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('potentialid', $potentialId)
            ->exists();
    }

    public function getNextPotentialNumber(): string
    {
        $count = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->count();

        return 'POT' . date('Y') . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // Interface Implementation: OpportunityRepositoryInterface
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?int $accountId = null,
        ?string $sortBy = null,
        ?string $sortOrder = null
    ): LengthAwarePaginator {
        $allowedSortColumns = [
            'potentialname',
            'amount',
            'closingdate',
            'sales_stage',
            'probability',
            'createdtime',
            'modifiedtime',
            'accountname',
            'first_name',
            'last_name',
            'user_name',
        ];

        $sortBy = $sortBy && in_array($sortBy, $allowedSortColumns, true)
            ? $sortBy
            : 'createdtime';

        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $query = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_potential.*',
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
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Potentials');
        //filters
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_potential.potentialname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_potential.potential_no', 'LIKE', "%{$search}%");
            });
        }

        if ($accountId !== null) {
            $query->where('vtiger_potential.related_to', $accountId);
        }

        $sortColumnMap = [
            'accountname' => 'vtiger_account.accountname',
            'first_name' => 'vtiger_users.first_name',
            'last_name' => 'vtiger_users.last_name',
            'user_name' => 'vtiger_users.user_name',
            'createdtime' => 'vtiger_crmentity.createdtime',
            'modifiedtime' => 'vtiger_crmentity.modifiedtime',

        ];

        $sortColumn = $sortColumnMap[$sortBy] ?? "vtiger_potential.{$sortBy}";
        $query->orderBy($sortColumn, $sortOrder);

        $total = $query->count();
        //$items = $query->forPage($page, $perPage)->orderBy('vtiger_potential.potentialname')->get();
        $items = $query->forPage($page, $perPage)->get();

        $opportunities = $items->map(function ($row) {
            $userName = null;
            if ($row->first_name || $row->last_name) {
                $userName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            } elseif ($row->user_name) {
                $userName = $row->user_name;
            }

            return new Opportunity(
                potentialid: (int) $row->potentialid,
                potential_no: (string) $row->potential_no,
                potentialname: (string) $row->potentialname,
                amount: $row->amount ? (float) $row->amount : null,
                closingdate: $row->closingdate,
                sales_stage: (string) $row->sales_stage,
                probability: $row->probability ? (int) $row->probability : null,
                related_to: $row->related_to ? (int) $row->related_to : null,
                related_to_name: $row->accountname ?? null,
                assigned_user_id: $row->smownerid ? (int) $row->smownerid : null,
                assigned_user_name: $userName,
                description: $row->description ?? null,
            );
        });

        return new LengthAwarePaginator(
            $opportunities,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Opportunity
    {
        if ($id <= 0) {
            return null;
        }

        $row = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_potential.*',
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
            ->where('vtiger_potential.potentialid', $id)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (! $row) {
            return null;
        }

        return new Opportunity(
            potentialid: (int) $row->potentialid,
            potential_no: (string) $row->potential_no,
            potentialname: (string) $row->potentialname,
            amount: $row->amount ? (float) $row->amount : null,
            closingdate: $row->closingdate,
            sales_stage: (string) $row->sales_stage,
            probability: $row->probability ? (int) $row->probability : null,
            related_to: $row->related_to ? (int) $row->related_to : null,
            related_to_name: $row->accountname ?? null,
            assigned_user_id: $row->smownerid ? (int) $row->smownerid : null,
            assigned_user_name: $row->user_name ?? null,
            description: $row->description ?? null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateOpportunityRequest $request, int $createdByUserId): int
    {
        throw new RuntimeException('Use CreateOpportunityUseCase for opportunity creation');
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, array $data, int $modifiedByUserId): bool
    {
        throw new RuntimeException('Use UpdateOpportunityUseCase for opportunity updates');
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id, int $deletedByUserId): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Opportunity ID must be positive');
        }

        $updated = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $id)
            ->where('setype', 'Potentials')
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableStages(): array
    {
        return [
            'Prospecting',
            'Qualification',
            'Needs Analysis',
            'Value Proposition',
            'Decision Makers',
            'Proposal/Price Quote',
            'Negotiation/Review',
            'Closed Won',
            'Closed Lost',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query, int $limit): array
    {
        $rows = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->select(
                'vtiger_potential.potentialid',
                'vtiger_potential.potentialname',
                'vtiger_potential.potential_no',
                'vtiger_potential.amount',
                'vtiger_potential.sales_stage',
                'vtiger_account.accountname'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Potentials')
            ->where('vtiger_potential.potentialname', 'LIKE', "%{$query}%")
            ->limit($limit)
            ->get();

        return $rows->toArray();
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
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_potential.related_to', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function validateRequiredFields(array $data): void
    {
        $required = ['potentialid', 'potentialname'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
    }

    private function prepareData(array $data): array
    {
        return [
            'potentialid' => $data['potentialid'],
            'potential_no' => $data['potential_no'] ?? null,
            'related_to' => $data['related_to'] ?? null,
            'potentialname' => $data['potentialname'],
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'closingdate' => $data['closingdate'] ?? null,
            'typeofrevenue' => $data['typeofrevenue'] ?? null,
            'nextstep' => $data['nextstep'] ?? null,
            'private' => $data['private'] ?? '0',
            'probability' => $data['probability'] ?? null,
            'campaignid' => $data['campaignid'] ?? null,
            'sales_stage' => $data['sales_stage'] ?? 'Prospecting',
            'potentialtype' => $data['potentialtype'] ?? null,
            'leadsource' => $data['leadsource'] ?? null,
            'productid' => $data['productid'] ?? null,
            'productversion' => $data['productversion'] ?? null,
            'quotationref' => $data['quotationref'] ?? null,
            'partnercontact' => $data['partnercontact'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'runtimefee' => $data['runtimefee'] ?? null,
            'followupdate' => $data['followupdate'] ?? null,
            'evaluationstatus' => $data['evaluationstatus'] ?? null,
            'description' => $data['description'] ?? null,
            'forecastcategory' => $data['forecastcategory'] ?? null,
            'outcomeanalysis' => $data['outcomeanalysis'] ?? null,
            'forecast_amount' => $data['forecast_amount'] ?? null,
            'isconvertedfromlead' => $data['isconvertedfromlead'] ?? '0',
            'contact_id' => $data['contact_id'] ?? null,
            'tags' => $data['tags'] ?? null,
            'converted' => $data['converted'] ?? '0',
        ];
    }
}
