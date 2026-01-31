<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\CreateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Domain\Entities\Client;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class VtigerClientRepository implements ClientRepositoryInterface
{
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
                'vtiger_crmentity.deleted as crm_deleted',
                // address of invoice
                'vtiger_accountbillads.bill_street',
                'vtiger_accountbillads.bill_city',
                'vtiger_accountbillads.bill_state',
                'vtiger_accountbillads.bill_code',
                'vtiger_accountbillads.bill_country',
                'vtiger_accountbillads.bill_pobox',
                // address of shipping
                'vtiger_accountshipads.ship_street',
                'vtiger_accountshipads.ship_city',
                'vtiger_accountshipads.ship_state',
                'vtiger_accountshipads.ship_code',
                'vtiger_accountshipads.ship_country',
                'vtiger_accountshipads.ship_pobox'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Accounts');

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_account.accountname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.account_no', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.email1', 'LIKE', "%{$search}%");
            });
        }

        // Filters (eg: by industry, rating, etc.)
        if ($filters) {
            foreach ($filters as $field => $value) {
                if (!empty($value)) {
                    $query->where("vtiger_account.{$field}", $value);
                }
            }
        }


        // $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $clients = $items->map(fn($row) => new Client(
            accountid: $row->accountid,
            account_no: $row->account_no,
            accountname: $row->accountname,
            parentid: $row->parentid,
            account_type: $row->account_type,
            industry: $row->industry,
            annualrevenue: $row->annualrevenue,
            rating: $row->rating,
            ownership: $row->ownership,
            siccode: $row->siccode,
            tickersymbol: $row->tickersymbol,
            phone: $row->phone,
            otherphone: $row->otherphone,
            email1: $row->email1,
            email2: $row->email2,
            website: $row->website,
            fax: $row->fax,
            employees: $row->employees,
            emailoptout: $row->emailoptout,
            notify_owner: $row->notify_owner,
            isconvertedfromlead: $row->isconvertedfromlead,
            tags: $row->tags,
            isActive: $row->crm_deleted === 0,
            // address of invoice
            bill_street: $row->bill_street,
            bill_city: $row->bill_city,
            bill_state: $row->bill_state,
            bill_code: $row->bill_code,
            bill_country: $row->bill_country,
            bill_pobox: $row->bill_pobox,
            // address of shipping
            ship_street: $row->ship_street,
            ship_city: $row->ship_city,
            ship_state: $row->ship_state,
            ship_code: $row->ship_code,
            ship_country: $row->ship_country,
            ship_pobox: $row->ship_pobox,
        ));



        return new LengthAwarePaginator(
            $clients instanceof Collection ? $clients : collect($clients),
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function findById(int $id): ?Client
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_account')
            ->join('vtiger_crmentity', 'vtiger_account.accountid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_accountbillads', 'vtiger_account.accountid', '=', 'vtiger_accountbillads.accountaddressid')
            ->leftJoin('vtiger_accountshipads', 'vtiger_account.accountid', '=', 'vtiger_accountshipads.accountaddressid')
            ->select(
                'vtiger_account.*',
                'vtiger_crmentity.deleted as crm_deleted',
                // address of invoice
                'vtiger_accountbillads.bill_street',
                'vtiger_accountbillads.bill_city',
                'vtiger_accountbillads.bill_state',
                'vtiger_accountbillads.bill_code',
                'vtiger_accountbillads.bill_country',
                'vtiger_accountbillads.bill_pobox',
                //  address of shipping
                'vtiger_accountshipads.ship_street',
                'vtiger_accountshipads.ship_city',
                'vtiger_accountshipads.ship_state',
                'vtiger_accountshipads.ship_code',
                'vtiger_accountshipads.ship_country',
                'vtiger_accountshipads.ship_pobox'
            )
            ->where('vtiger_account.accountid', $id)
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Accounts')
            ->first();

        return $row ? new Client(
            accountid: $row->accountid,
            account_no: $row->account_no,
            accountname: $row->accountname,
            parentid: $row->parentid,
            account_type: $row->account_type,
            industry: $row->industry,
            annualrevenue: $row->annualrevenue,
            rating: $row->rating,
            ownership: $row->ownership,
            siccode: $row->siccode,
            tickersymbol: $row->tickersymbol,
            phone: $row->phone,
            otherphone: $row->otherphone,
            email1: $row->email1,
            email2: $row->email2,
            website: $row->website,
            fax: $row->fax,
            employees: $row->employees,
            emailoptout: $row->emailoptout,
            notify_owner: $row->notify_owner,
            isconvertedfromlead: $row->isconvertedfromlead,
            tags: $row->tags,
            isActive: $row->crm_deleted === 0,
            // address of invoice
            bill_street: $row->bill_street,
            bill_city: $row->bill_city,
            bill_state: $row->bill_state,
            bill_code: $row->bill_code,
            bill_country: $row->bill_country,
            bill_pobox: $row->bill_pobox,
            // address of shipping
            ship_street: $row->ship_street,
            ship_city: $row->ship_city,
            ship_state: $row->ship_state,
            ship_code: $row->ship_code,
            ship_country: $row->ship_country,
            ship_pobox: $row->ship_pobox,
        ) : null;
    }

    public function create(CreateClientRequest $request, int $userId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $nextCrmId = $this->getNextCrmId();
            $currentTime = now()->format('Y-m-d H:i:s');
            $accountNo = $request->account_no ?? $this->generateAccountNumber($nextCrmId);
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $nextCrmId,
                    'deleted' => 0,
                    'setype' => 'Accounts',
                    'createdtime' => $currentTime,
                    'modifiedtime' => $currentTime,
                    'smcreatorid' => $userId,
                    'smownerid' => $userId,
                    'modifiedby' => $userId
                ]);

            
            DB::connection('vtiger')
                ->table('vtiger_account')
                ->insert([
                    'accountid' => $nextCrmId,
                    'accountname' => $request->accountname,
                    'account_no' => $accountNo,
                    'account_type' => $request->account_type,
                    'industry' => $request->industry,
                    'annualrevenue' => $request->annualrevenue,
                    'rating' => $request->rating,
                    'ownership' => $request->ownership,
                    'siccode' => $request->siccode,
                    'tickersymbol' => $request->tickersymbol,
                    'phone' => $request->phone,
                    'otherphone' => $request->otherphone,
                    'email1' => $request->email1,
                    'email2' => $request->email2,
                    'website' => $request->website,
                    'fax' => $request->fax,
                    'employees' => $request->employees,
                    'emailoptout' => $request->emailoptout,
                    'notify_owner' => $request->notify_owner,
                    'isconvertedfromlead' => $request->isconvertedfromlead,
                    'tags' => $request->tags,
                ]);

            
            if ($this->hasBillingAddress($request)) {
                DB::connection('vtiger')
                    ->table('vtiger_accountbillads')
                    ->insert([
                        'accountaddressid' => $nextCrmId,
                        'bill_street' => $request->bill_street,
                        'bill_city' => $request->bill_city,
                        'bill_state' => $request->bill_state,
                        'bill_code' => $request->bill_code,
                        'bill_country' => $request->bill_country,
                        'bill_pobox' => $request->bill_pobox,
                    ]);
            }

           
            if ($this->hasShippingAddress($request)) {
                DB::connection('vtiger')
                    ->table('vtiger_accountshipads')
                    ->insert([
                        'accountaddressid' => $nextCrmId,
                        'ship_street' => $request->ship_street,
                        'ship_city' => $request->ship_city,
                        'ship_state' => $request->ship_state,
                        'ship_code' => $request->ship_code,
                        'ship_country' => $request->ship_country,
                        'ship_pobox' => $request->ship_pobox,
                    ]);
            }

            DB::connection('vtiger')->commit();
            return $nextCrmId;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    private function hasBillingAddress(CreateClientRequest $request): bool
    {
        return !empty($request->bill_street) ||
            !empty($request->bill_city) ||
            !empty($request->bill_state) ||
            !empty($request->bill_code) ||
            !empty($request->bill_country) ||
            !empty($request->bill_pobox);
    }

    private function hasShippingAddress(CreateClientRequest $request): bool
    {
        return !empty($request->ship_street) ||
            !empty($request->ship_city) ||
            !empty($request->ship_state) ||
            !empty($request->ship_code) ||
            !empty($request->ship_country) ||
            !empty($request->ship_pobox);
    }

    private function getNextCrmId(): int
    {
        $maxCrmId = DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid');
        return $maxCrmId ? $maxCrmId + 1 : 1;
    }

    private function generateAccountNumber(int $crmid): string
    {

        return 'ACC-' . str_pad($crmid, 6, '0', STR_PAD_LEFT);
    }

  
    private function accountNumberExists(string $accountNo): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_account')
            ->where('account_no', $accountNo)
            ->exists();
    }
}
