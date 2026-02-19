<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\DTOs\QuoteResponse;
use App\Application\Repositories\QuoteRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class VtigerQuoteRepository implements QuoteRepositoryInterface
{
    public function create(CreateQuoteRequest $request, int $createdByUserId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');

            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;
            $quoteNo = $this->generateQuoteNumber();

            $subtotal = 0;
            $itemsWithTotals = [];

            foreach ($request->items as $item) {
                $netprice = $item['listprice'] * (1 - ($item['discount_percent'] ?? 0) / 100);
                $total = ($item['quantity'] ?? 0) * $netprice;
                $subtotal += $total;

                $itemsWithTotals[] = [
                    'productid' => $item['productid'],
                    'sequence_no' => $item['sequence_no'],
                    'productname' => $item['productname'],
                    'quantity' => $item['quantity'],
                    'listprice' => $item['listprice'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'description' => $item['description'],
                ];
            }


            $itbms = $subtotal * 0.07;
            $totalWithTax = $subtotal + $itbms;

            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $crmid,
                    'smownerid' => $request->assigned_user_id,
                    'smcreatorid' => $createdByUserId,
                    'setype' => 'Quotes',
                    'description' => $request->description,
                    'createdtime' => now()->format('Y-m-d H:i:s'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    'deleted' => 0,
                ]);

            DB::connection('vtiger')
                ->table('vtiger_quotes')
                ->insert([
                    'quoteid' => $crmid,
                    'quote_no' => $quoteNo,
                    'subject' => $request->subject,
                    'potentialid' => $request->potentialid,
                    'accountid' => $request->accountid,
                    'quotestage' => 'Draft',
                    'validtill' => $request->validtill,
                    'subtotal' => $subtotal,
                    'discount_percent' => 0,
                    'total' => $totalWithTax,
                    'carrier' => null,
                    'shipping' => null,
                    'inventorymanager' => null,
                    'type' => null,
                    'adjustment' => null,
                    'taxtype' => 'individual',
                    'discount_amount' => null,
                    's_h_amount' => null,
                    'terms_conditions' => null,
                    'currency_id' => 1,
                    'conversion_rate' => 1.000,
                    'compound_taxes_info' => json_encode(['tax1' => $itbms]),
                    'pre_tax_total' => $subtotal,
                    's_h_percent' => null,
                    'tags' => null,
                    'region_id' => null,
                ]);

            foreach ($itemsWithTotals as $item) {
                DB::connection('vtiger')
                    ->table('vtiger_inventoryproductrel')
                    ->insert([
                        'id' => $crmid,
                        'productid' => $item['productid'],
                        'sequence_no' => $item['sequence_no'],
                        'quantity' => $item['quantity'],
                        'listprice' => $item['listprice'],
                        'discount_percent' => $item['discount_percent'],
                        'description' => $item['productname'],
                        'comment' => $item['description'] ?? null,
                        'incrementondel' => 0,
                    ]);
            }

            $hasTax = false;
            foreach ($request->items as $item) {
                if (!empty($item['taxes'])) {
                    $hasTax = true;
                    break;
                }
            }

            if (!$hasTax) {
                DB::connection('vtiger')
                    ->table('vtiger_inventorytaxinfo')
                    ->insert([
                        'id' => $crmid,
                        'module' => 'Quotes',
                        'taxname' => 'tax1',
                        'percentage' => 7.000,
                        'compoundon' => '',
                        'deleted' => 0,
                    ]);
            }

            DB::connection('vtiger')->commit();
            return $crmid;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function update(UpdateQuoteRequest $request, int $modifiedByUserId): bool
    {
        DB::connection('vtiger')->beginTransaction();

        try {
           
            $subtotal = 0;
            $itemsWithTotals = [];

            foreach ($request->items as $item) {
                $netprice = $item['listprice'] * (1 - ($item['discount_percent'] ?? 0) / 100);
                $total = ($item['quantity'] ?? 0) * $netprice;
                $subtotal += $total;

                $itemsWithTotals[] = [
                    'productid' => $item['productid'],
                    'sequence_no' => $item['sequence_no'],
                    'productname' => $item['productname'],
                    'quantity' => $item['quantity'],
                    'listprice' => $item['listprice'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'description' => $item['description'],
                ];
            }

           
            $itbms = $subtotal * 0.07;
            $totalWithTax = $subtotal + $itbms;

            
            DB::connection('vtiger')
                ->table('vtiger_quotes')
                ->where('quoteid', $request->quoteid)
                ->update([
                    'subject' => $request->subject,
                    'potentialid' => $request->potentialid,
                    'accountid' => $request->accountid,
                    'quotestage' => $request->quote_stage,
                    'validtill' => $request->validtill,
                    'subtotal' => $subtotal,
                    'discount_percent' => 0, // Ajustar si hay descuentos
                    'total' => $totalWithTax,
                    'taxtype' => 'individual',
                    'compound_taxes_info' => json_encode(['tax1' => $itbms]),
                    'pre_tax_total' => $subtotal,
                ]);

           
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $request->quoteid)
                ->update([
                    'smownerid' => $request->assigned_user_id,
                    'description' => $request->description,
                ]);

            
            DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->where('id', $request->quoteid)
                ->delete();

           
            foreach ($itemsWithTotals as $item) {
                DB::connection('vtiger')
                    ->table('vtiger_inventoryproductrel')
                    ->insert([
                        'id' => $request->quoteid,
                        'productid' => $item['productid'],
                        'sequence_no' => $item['sequence_no'],
                        'quantity' => $item['quantity'],
                        'listprice' => $item['listprice'],
                        'discount_percent' => $item['discount_percent'],
                        'description' => $item['productname'],
                        'comment' => $item['description'] ?? null,
                        'incrementondel' => 0,
                    ]);
            }

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function findById(int $quoteid): ?QuoteResponse
    {
        // Verificar que el ID sea una cotización
        $isQuote = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $quoteid)
            ->where('setype', 'Quotes')
            ->exists();

        if (!$isQuote) {
            return null;
        }

        $quoteRow = DB::connection('vtiger')
            ->table('vtiger_quotes as q')
            ->join('vtiger_crmentity as c', 'q.quoteid', '=', 'c.crmid')
            ->leftJoin('vtiger_account as a', 'q.accountid', '=', 'a.accountid')
            ->select(
                'q.quoteid',
                'q.quote_no as quoteno',
                'q.subject',
                'q.potentialid',
                'q.accountid',
                'c.smownerid as assigned_user_id',
                'q.quotestage as quote_stage',
                'q.validtill',
                'c.description',
                'q.subtotal',
                'q.discount_percent',
                'q.total',
                'c.createdtime',
                'c.modifiedtime',
                'a.accountname' // 
            )
            ->where('q.quoteid', $quoteid)
            ->first();

        if (!$quoteRow) {
            return null;
        }


        $items = DB::connection('vtiger')
            ->table('vtiger_inventoryproductrel')
            ->where('id', $quoteid)
            ->orderBy('sequence_no')
            ->get()
            ->map(function ($item) {
                $netprice = $item->listprice * (1 - ($item->discount_percent ?? 0) / 100);
                $total = ($item->quantity ?? 0) * $netprice;

                return [
                    'productid' => $item->productid,
                    'sequence_no' => (int) $item->sequence_no,
                    'productname' => $item->description ?? '',
                    'quantity' => (float) $item->quantity,
                    'listprice' => (float) $item->listprice,
                    'discount_percent' => (float) $item->discount_percent,
                    'netprice' => $netprice,
                    'total' => $total,
                    'description' => $item->comment ?? null,
                ];
            })
            ->toArray();



        $potentialName = null;
        $assignedUserName = null;

        $accountName = $quoteRow->accountname ?? null;

        // (potential)
        if ($quoteRow->potentialid) {
            $potential = DB::connection('vtiger')
                ->table('vtiger_potential')
                ->where('potentialid', $quoteRow->potentialid)
                ->first();
            $potentialName = $potential?->potentialname;
        }

        // User assigned
        if ($quoteRow->assigned_user_id) {
            $user = DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $quoteRow->assigned_user_id)
                ->first();
            $assignedUserName = $user ? trim($user->first_name . ' ' . $user->last_name) : null;
        }

        return new QuoteResponse(
            quoteid: $quoteRow->quoteid,
            quoteno: $quoteRow->quoteno,
            subject: $quoteRow->subject,
            potentialid: $quoteRow->potentialid,
            accountid: $quoteRow->accountid,
            assigned_user_id: $quoteRow->assigned_user_id,
            quote_stage: $quoteRow->quote_stage,
            validtill: $quoteRow->validtill,
            description: $quoteRow->description,
            subtotal: (float) $quoteRow->subtotal,
            discount_percent: $quoteRow->discount_percent !== null ? (float) $quoteRow->discount_percent : null,
            total: (float) $quoteRow->total,
            createdtime: $quoteRow->createdtime,
            modifiedtime: $quoteRow->modifiedtime,
            items: $items,
            account_name: $accountName,
            potential_name: $potentialName,
            assigned_user_name: $assignedUserName
        );
    }

    public function delete(int $quoteid): bool
    {
        return DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $quoteid)
            ->where('setype', 'Quotes')
            ->update(['deleted' => 1]) > 0;
    }

    public function paginate(int $page, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_quotes as q')
            ->join('vtiger_crmentity as c', 'q.quoteid', '=', 'c.crmid')
            ->leftJoin('vtiger_account as a', 'q.accountid', '=', 'a.accountid')
            ->select(
                'q.quoteid',
                'q.quote_no as quoteno',
                'q.subject',
                'q.potentialid',
                'q.accountid',
                'c.smownerid as assigned_user_id',
                'q.quotestage as quote_stage',
                'q.validtill',
                'c.description',
                'q.subtotal',
                'q.discount_percent',
                'q.total',
                'c.createdtime',
                'c.modifiedtime',
                'a.accountname' // 👈 Incluir nombre del cliente
            )
            ->where('c.deleted', 0);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('q.subject', 'like', "%{$search}%")
                    ->orWhere('q.quote_no', 'like', "%{$search}%")
                    ->orWhere('a.accountname', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $quotes = $query
            ->orderBy('c.createdtime', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        if ($quotes->isEmpty()) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query()
            ]);
        }

        // Obtener solo IDs que son cotizaciones
        $quoteIds = $quotes->pluck('quoteid')->toArray();
        $validQuoteIds = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->whereIn('crmid', $quoteIds)
            ->where('setype', 'Quotes')
            ->pluck('crmid')
            ->toArray();

        // Cargar ítems solo para cotizaciones válidas
        $items = [];
        if (!empty($validQuoteIds)) {
            $items = DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->whereIn('id', $validQuoteIds)
                ->orderBy('sequence_no')
                ->get()
                ->groupBy('id');
        }


        $potentialIds = $quotes->pluck('potentialid')->unique()->filter()->toArray();
        $userIds = $quotes->pluck('assigned_user_id')->unique()->filter()->toArray();

        $potentials = !empty($potentialIds)
            ? DB::connection('vtiger')->table('vtiger_potential')->whereIn('potentialid', $potentialIds)->pluck('potentialname', 'potentialid')->toArray()
            : [];

        $users = !empty($userIds)
            ? DB::connection('vtiger')->table('vtiger_users')->whereIn('id', $userIds)->get()->mapWithKeys(fn($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)])->toArray()
            : [];

        // Convertir a entidades
        $entities = $quotes->map(function ($quote) use ($items, $potentials, $users) {
            $quoteItems = [];
            if (isset($items[$quote->quoteid])) {
                $quoteItems = $items[$quote->quoteid]->map(function ($item) {
                    $netprice = $item->listprice * (1 - ($item->discount_percent ?? 0) / 100);
                    $total = ($item->quantity ?? 0) * $netprice;

                    return [
                        'productid' => $item->productid,
                        'sequence_no' => (int) $item->sequence_no,
                        'productname' => $item->description ?? '',
                        'quantity' => (float) $item->quantity,
                        'listprice' => (float) $item->listprice,
                        'discount_percent' => (float) $item->discount_percent,
                        'netprice' => $netprice,
                        'total' => $total,
                        'description' => $item->comment ?? null,
                    ];
                })->toArray();
            }

            return new QuoteResponse(
                quoteid: $quote->quoteid,
                quoteno: $quote->quoteno,
                subject: $quote->subject,
                potentialid: $quote->potentialid,
                accountid: $quote->accountid,
                assigned_user_id: $quote->assigned_user_id,
                quote_stage: $quote->quote_stage,
                validtill: $quote->validtill,
                description: $quote->description,
                subtotal: (float) $quote->subtotal,
                discount_percent: $quote->discount_percent !== null ? (float) $quote->discount_percent : null,
                total: (float) $quote->total,
                createdtime: $quote->createdtime,
                modifiedtime: $quote->modifiedtime,
                items: $quoteItems,
                account_name: $quote->accountname ?? null, // 👈 Nombre del cliente desde la consulta principal
                potential_name: $quote->potentialid ? ($potentials[$quote->potentialid] ?? null) : null,
                assigned_user_name: $users[$quote->assigned_user_id] ?? null
            );
        })->toArray();

        return new LengthAwarePaginator($entities, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query()
        ]);
    }

    private function generateQuoteNumber(): string
    {
        $currentYear = date('y');
        $expectedPrefix = "C-{$currentYear}-";

        $sequenceRow = DB::connection('vtiger')
            ->table('vtiger_modentity_num')
            ->where('semodule', 'Quotes')
            ->where('active', 1)
            ->first();

        if ($sequenceRow) {
            if ($sequenceRow->prefix !== $expectedPrefix) {
                // El año ha cambiado, actualizar el prefijo y reiniciar la secuencia
                $newCurId = 1; // Reiniciar desde 1 para el nuevo año

                DB::connection('vtiger')
                    ->table('vtiger_modentity_num')
                    ->where('num_id', $sequenceRow->num_id)
                    ->update([
                        'prefix' => $expectedPrefix,
                        'cur_id' => $newCurId
                    ]);

                $formattedId = str_pad($newCurId, 5, '0', STR_PAD_LEFT);
                return $expectedPrefix . $formattedId;
            }


            $nextId = $sequenceRow->cur_id + 1;

            DB::connection('vtiger')
                ->table('vtiger_modentity_num')
                ->where('num_id', $sequenceRow->num_id)
                ->update(['cur_id' => $nextId]);

            $formattedId = str_pad($nextId, 5, '0', STR_PAD_LEFT);
            return $sequenceRow->prefix . $formattedId;
        }


        $newCurId = 1;
        $formattedId = str_pad($newCurId, 5, '0', STR_PAD_LEFT);

        DB::connection('vtiger')
            ->table('vtiger_modentity_num')
            ->insert([
                'semodule' => 'Quotes',
                'prefix' => $expectedPrefix,
                'start_id' => 1,
                'cur_id' => $newCurId,
                'active' => 1
            ]);

        return $expectedPrefix . $formattedId;
    }
}
