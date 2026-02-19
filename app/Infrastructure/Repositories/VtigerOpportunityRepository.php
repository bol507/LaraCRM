<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Application\DTOs\CreateOpportunityRequest;
use App\Domain\Entities\Opportunity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class VtigerOpportunityRepository implements OpportunityRepositoryInterface
{
    public function getAll(int $page = 1, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_potential.potentialid',
                'vtiger_potential.potential_no',
                'vtiger_potential.potentialname',
                'vtiger_potential.amount',
                'vtiger_potential.closingdate',
                'vtiger_potential.sales_stage',
                'vtiger_potential.probability',
                'vtiger_potential.related_to',
                'vtiger_account.accountname as related_to_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                 DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_potential.description',
                'vtiger_crmentity.deleted'
            )
            ->where('vtiger_crmentity.deleted', 0);

        if ($search) {
            $query->where('vtiger_potential.potentialname', 'LIKE', "%{$search}%");
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $opportunities = $items->map(fn($row) => new Opportunity(
            potentialid: $row->potentialid,
            potential_no: $row->potential_no,
            potentialname: $row->potentialname,
            amount: $row->amount ? (float)$row->amount : null,
            closingdate: $row->closingdate,
            sales_stage: $row->sales_stage,
            probability: $row->probability ? (int)$row->probability : null,
            related_to: $row->related_to ? (int)$row->related_to : null,
            related_to_name: $row->related_to_name ?? null,
            assigned_user_id: $row->assigned_user_id ? (int)$row->assigned_user_id : null,
            assigned_user_name: $row->assigned_user_name && trim($row->assigned_user_name) !== ' ' 
            ? $row->assigned_user_name 
            : null,
            description: $row->description,
            is_active: true
        ));

        return new LengthAwarePaginator(
            $opportunities instanceof Collection ? $opportunities : collect($opportunities),
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function findById(int $id): ?Opportunity
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_potential.related_to', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_potential.potentialid',
                'vtiger_potential.potential_no',
                'vtiger_potential.potentialname',
                'vtiger_potential.amount',
                'vtiger_potential.closingdate',
                'vtiger_potential.sales_stage',
                'vtiger_potential.probability',
                'vtiger_potential.related_to',
                'vtiger_account.accountname as related_to_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                 DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_potential.description',
                'vtiger_crmentity.deleted'
            )
            ->where('vtiger_potential.potentialid', $id)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        return $row ? new Opportunity(
            potentialid: $row->potentialid,
            potential_no: $row->potential_no,
            potentialname: $row->potentialname,
            amount: $row->amount ? (float)$row->amount : null,
            closingdate: $row->closingdate,
            sales_stage: $row->sales_stage,
            probability: $row->probability ? (int)$row->probability : null,
            related_to: $row->related_to ? (int)$row->related_to : null,
            related_to_name: $row->related_to_name ?? null,
            assigned_user_id: $row->assigned_user_id ? (int)$row->assigned_user_id : null,
            assigned_user_name: $row->assigned_user_name && trim($row->assigned_user_name) !== ' ' 
            ? $row->assigned_user_name 
            : null,
            description: $row->description,
            is_active: true
        ) : null;
    }

    public function create(CreateOpportunityRequest $request, int $createdByUserId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $maxCrmid = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->max('crmid');
        
            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;
            DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->insert([
                'crmid' => $crmid, // 👈 Valor explícito requerido
                'smownerid' => $request->assigned_user_id ?? $createdByUserId,
                'smcreatorid' => $createdByUserId,
                'setype' => 'Potentials',
                'description' => $request->description ?? '',
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);

            // Luego crear en vtiger_potential
            DB::connection('vtiger')
                ->table('vtiger_potential')
                ->insert([
                'potentialid' => $crmid,
                'potential_no' => 'POT' . str_pad($crmid, 8, '0', STR_PAD_LEFT),
                'potentialname' => $request->potentialname,
                'amount' => $request->amount,
                'closingdate' => $request->closingdate,
                'sales_stage' => $request->sales_stage,
                'probability' => $request->probability,
                'related_to' => $request->related_to,
                'description' => $request->description ?? '',
                'isconvertedfromlead' => 0,
                'converted' => 0,
            ]);

            DB::connection('vtiger')->commit();
            return $crmid;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function update(int $id, array $data, int $modifiedByUserId): bool
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            // Actualizar vtiger_crmentity (usuario asignado)
            if (isset($data['assigned_user_id'])) {
                DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $id)
                    ->update([
                        'smownerid' => $data['assigned_user_id'], // 👈 smownerid, no assigned_user_id
                        'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    ]);
            }

            // Actualizar vtiger_potential (sin assigned_user_id)
            $updateData = [];
            if (isset($data['potentialname'])) $updateData['potentialname'] = $data['potentialname'];
            if (isset($data['amount'])) $updateData['amount'] = $data['amount'];
            if (isset($data['closingdate'])) $updateData['closingdate'] = $data['closingdate'];
            if (isset($data['sales_stage'])) $updateData['sales_stage'] = $data['sales_stage'];
            if (isset($data['probability'])) $updateData['probability'] = $data['probability'];
            if (isset($data['related_to'])) $updateData['related_to'] = $data['related_to'];
            if (isset($data['description'])) $updateData['description'] = $data['description'];

            if (!empty($updateData)) {
                DB::connection('vtiger')
                    ->table('vtiger_potential')
                    ->where('potentialid', $id)
                    ->update($updateData);
            }

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function delete(int $id, int $deletedByUserId): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $id)
            ->where('setype', 'Potentials')
            ->update(['deleted' => 1]) > 0;
    }

    public function getAvailableStages(): array
    {
        return [
            'Prospecting',
            'Qualification',
            'Needs Analysis',
            'Value Proposition',
            'Identifying Decision Makers',
            'Perception Analysis',
            'Proposal/Price Quote',
            'Negotiation/Review',
            'Closed Won',
            'Closed Lost'
        ];
    }
}
