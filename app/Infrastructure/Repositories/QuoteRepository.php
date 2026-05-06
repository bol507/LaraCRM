<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\Quote\QuoteResponse;
use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\Repositories\QuoteRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Repository for vtiger_quotes - Quote data table
 *
 * Handles CRUD operations for quote data in Vtiger CRM.
 * This repository implements QuoteRepositoryInterface.
 */
class QuoteRepository implements QuoteRepositoryInterface
{
    private const TABLE = 'vtiger_quotes';

    private const CONNECTION = 'vtiger';

    private const TAX = 0.07;

    /**
     * @deprecated
     */
    public function create(CreateQuoteRequest $request, int $createdByUserId): int
    {
        $quoteId = $this->generateQuoteId();
        $subtotal = 0;
        $itemsWithTotals = [];
        
        if (! empty($request->items)) {
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
        }

        $itbms = $subtotal * self::TAX;
        $totalWithTax = $subtotal + $itbms;
        
        DB::connection(self::CONNECTION)->transaction(function () use ($request, $createdByUserId, $quoteId, $subtotal, $totalWithTax, $itbms, $itemsWithTotals) {
            // Insert into vtiger_quotes
            DB::connection(self::CONNECTION)->table(self::TABLE)->insert([
                'quoteid' => $quoteId,
                'quote_no' => 'Q' . date('Y') . str_pad($quoteId, 4, '0', STR_PAD_LEFT),
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

            // Insert into vtiger_crmentity
            DB::connection(self::CONNECTION)->table('vtiger_crmentity')->insert([
                'crmid' => $quoteId,
                'smcreatorid' => $createdByUserId,
                'smownerid' => $request->assigned_user_id ?? $createdByUserId,
                'setype' => 'Quotes',
                'label' => $request->subject,
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'modifiedby' => $createdByUserId,
                'deleted' => 0,
            ]);
            
            foreach ($itemsWithTotals as $item) {
                DB::connection(self::CONNECTION)
                    ->table('vtiger_inventoryproductrel')
                    ->insert([
                        'id' => $quoteId,
                        'productid' => $item['productid'],
                        'sequence_no' => $item['sequence_no'] ?? 1,
                        'quantity' => $item['quantity'],
                        'listprice' => $item['listprice'],
                        'discount_percent' => $item['discount_percent'] ?? 0,
                        'description' => $item['productname'] ?? '',
                        'comment' => $item['description'] ?? '',
                    ]);
            }
        });

        return $quoteId;
    }

    public function update(UpdateQuoteRequest $request, int $modifiedByUserId): bool
    {
        $quoteId = $request->quoteid;

        DB::connection(self::CONNECTION)->transaction(function () use ($request, $modifiedByUserId, $quoteId) {
            // Update vtiger_quotes
            $updateData = [];
            if ($request->subject !== null) {
                $updateData['subject'] = $request->subject;
            }
            if ($request->quote_stage !== null) {
                $updateData['quotestage'] = $request->quote_stage;
            }
            if ($request->validtill !== null) {
                $updateData['validtill'] = $request->validtill;
            }
            if (isset($request->accountid)) {
                $updateData['accountid'] = $request->accountid;
            }

            if (! empty($updateData)) {
                DB::connection(self::CONNECTION)->table(self::TABLE)
                    ->where('quoteid', $quoteId)
                    ->update($updateData);
            }

            // Update vtiger_crmentity
            $crmentityData = [
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'modifiedby' => $modifiedByUserId
            ];

            if ($request->subject !== null) {
                $crmentityData['label'] = $request->subject;
            }
            // description in vtiger_crmentity
            if (isset($request->description)) {
                $crmentityData['description'] = $request->description;
            }

            DB::connection(self::CONNECTION)->table('vtiger_crmentity')
                ->where('crmid', $quoteId)
                ->update($crmentityData);

            // Update line items if provided
            if (isset($request->items)) {
                DB::connection(self::CONNECTION)->table('vtiger_inventoryproductrel')
                    ->where('id', $quoteId)
                    ->delete();
                $subtotal = 0;
                $taxRate = self::TAX;

                foreach ($request->items as $index => $item) {
                    $quantity = (float) ($item['quantity'] ?? 0);
                    $listprice = (float) ($item['listprice'] ?? 0);
                    $discountPercent = (float) ($item['discount_percent'] ?? 0);

                    $netPrice = $listprice * (1 - $discountPercent / 100);
                    $itemTotal = $quantity * $netPrice;
                    $subtotal += $itemTotal;

                    DB::connection(self::CONNECTION)->table('vtiger_inventoryproductrel')->insert([
                        'id' => $quoteId,
                        'productid' => $item['productid'] ?? null,
                        'sequence_no' => $item['sequence_no'] ?? ($index + 1),
                        'quantity' => $item['quantity'],
                        'listprice' => $item['listprice'],
                        'discount_percent' => $item['discount_percent'] ?? 0,
                        'description' => $item['description'] ?? '',
                        'comment' => $item['comment'] ?? null,
                        'incrementondel' => 0,
                    ]);
                }

                $taxAmount = $subtotal * $taxRate;
                $total = $subtotal + $taxAmount;

                $updateData['subtotal'] = $subtotal;
                $updateData['total'] = $total;
                $updateData['compound_taxes_info'] = json_encode(['tax1' => $taxAmount]);
                $updateData['pre_tax_total'] = $subtotal;
            }
            
            if (! empty($updateData)) {
                DB::connection(self::CONNECTION)->table(self::TABLE)
                    ->where('quoteid', $quoteId)
                    ->update($updateData);
            }
        });

        return true;
    }

    public function findById(int $quoteid): ?QuoteResponse
    {
        $row = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_quotes.accountid', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_quotes.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.deleted',
                'vtiger_crmentity.smownerid as assigned_user_id',
                'vtiger_crmentity.description as quote_description',
                'vtiger_account.accountname',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_users.user_name'
            )
            ->where('vtiger_quotes.quoteid', $quoteid)
            ->first();

        if (! $row || $row->deleted == 1) {
            return null;
        }

        // Get line items
        $items = DB::connection(self::CONNECTION)
            ->table('vtiger_inventoryproductrel')
            ->leftJoin('vtiger_products', 'vtiger_inventoryproductrel.productid', '=', 'vtiger_products.productid')
            ->where('vtiger_inventoryproductrel.id', $quoteid)
            ->orderBy('vtiger_inventoryproductrel.sequence_no')
            ->get()
            ->map(function ($item) {
                $quantity = is_numeric($item->quantity) ? (float) $item->quantity : 0;
                $listprice = is_numeric($item->listprice) ? (float) $item->listprice : 0;
                $discountPercent = is_numeric($item->discount_percent) ? (float) $item->discount_percent : 0;

                $netprice = $listprice * (1 - ($discountPercent / 100));
                $total = $quantity * $netprice;

                $description = '';
                if (!empty($item->productid) && !empty($item->productname)) {
                    $description = $item->productname;
                    if (!empty($item->description) && $item->description !== $item->productname) {
                        $description .= ' - ' . $item->description;
                    }
                } elseif (!empty($item->description)) {
                    $description = $item->description;
                }

                return [
                    'productid' => $item->productid ? (int) $item->productid : null,
                    'sequence_no' => (int) ($item->sequence_no ?? 0),
                    'quantity' => $quantity,
                    'listprice' => $listprice,
                    'discount_percent' => $discountPercent,
                    'netprice' => $netprice,
                    'total' => $total,
                    'description' => $description, 
                    'comment' => $item->comment ?? null,
                ];
            })
            ->toArray();

        $potentialName = null;
        $assignedUserName = null;
        $accountName = $row->accountname ?? null;

        // Get potential name
        if ($row->potentialid) {
            $potential = DB::connection('vtiger')
                ->table('vtiger_potential')
                ->where('potentialid', $row->potentialid)
                ->first();
            $potentialName = $potential?->potentialname;
        }

        // Get assigned user name
        if ($row->assigned_user_id) {
            $user = DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $row->assigned_user_id)
                ->first();
            $assignedUserName = $user ? trim($user->first_name . ' ' . $user->last_name) : null;
        }

        return new QuoteResponse(
            quoteid: (int) $row->quoteid,
            quoteno: $row->quote_no,
            subject: $row->subject,
            quote_stage: $row->quotestage,
            accountid: $row->accountid ? (int) $row->accountid : null,
            assigned_user_id: $row->assigned_user_id ? (int) $row->assigned_user_id : null,
            validtill: $row->validtill,
            description: $row->quote_description ?? null,
            subtotal: $row->subtotal ? (float) $row->subtotal : null,
            total: $row->total ? (float) $row->total : null,
            createdtime: $row->createdtime,
            modifiedtime: $row->modifiedtime,
            items: $items,
            isActive: $row->deleted != 1,
            account_name: $accountName ?? '',
            potential_name: $potentialName ?? '',
            assigned_user_name: $assignedUserName ?? ''
        );
    }

    public function delete(int $quoteid): bool
    {
        if ($quoteid <= 0) {
            throw new InvalidArgumentException('Quote ID must be positive');
        }

        $updated = DB::connection(self::CONNECTION)
            ->table('vtiger_crmentity')
            ->where('crmid', $quoteid)
            ->where('setype', 'Quotes')
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    public function paginate(int $page, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        return $this->getAll($page, $perPage, $search);
    }

    public function search(string $query, int $limit): array
    {
        $rows = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_quotes.accountid', '=', 'vtiger_account.accountid')
            ->select(
                'vtiger_quotes.quoteid',
                'vtiger_quotes.quote_no',
                'vtiger_quotes.subject',
                'vtiger_quotes.quotestage',
                'vtiger_quotes.total',
                'vtiger_account.accountname'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Quotes')
            ->where(function ($q) use ($query) {
                $q->where('vtiger_quotes.subject', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_quotes.quote_no', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_account.accountname', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $rows->toArray();
    }

    public function countByClient(int $clientId): int
    {
        if ($clientId <= 0) {
            return 0;
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_quotes.accountid', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }

    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?int $accountId = null
    ): LengthAwarePaginator {
        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_quotes.accountid', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_quotes.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.deleted',
                'vtiger_crmentity.smownerid',
                'vtiger_account.accountname',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_users.user_name'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Quotes');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_quotes.subject', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_quotes.quote_no', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.accountname', 'LIKE', "%{$search}%");
            });
        }

        if ($accountId) {
            $query->where('vtiger_quotes.accountid', $accountId);
        }

        $total = $query->count();
        $quotes = $query->orderByDesc('vtiger_crmentity.createdtime')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $quoteResponses = $quotes->map(function ($row) {
            $userName = null;
            if ($row->first_name || $row->last_name) {
                $userName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            } elseif ($row->user_name) {
                $userName = $row->user_name;
            }

            return new QuoteResponse(
                quoteid: (int) $row->quoteid,
                quoteno: $row->quote_no,
                subject: $row->subject,
                quote_stage: $row->quotestage,
                accountid: $row->accountid ? (int) $row->accountid : null,
                assigned_user_id: $row->smownerid ? (int) $row->smownerid : null,
                validtill: $row->validtill,
                subtotal: $row->subtotal ? (float) $row->subtotal : null,
                total: $row->total ? (float) $row->total : null,
                createdtime: $row->createdtime,
                modifiedtime: $row->modifiedtime,
                items: [],
                account_name: $row->accountname ?? null,
                assigned_user_name: $userName,
                isActive: $row->deleted != 1
            );
        });

        return new LengthAwarePaginator(
            $quoteResponses,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function generateQuoteId(): int
    {
        $maxId = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->max('quoteid');

        return $maxId ? $maxId + 1 : 1;
    }

    public function insert(array $data, array $items = []): int
    {
        $subtotal = 0;
        $tax = self::TAX;

        // Insert items first to calculate totals
        if (! empty($items)) {
            foreach ($items as $index => $item) {
                $quantity = is_string($item['quantity']) ? (float) $item['quantity'] : ($item['quantity'] ?? 0);
                $listprice = is_string($item['listprice']) ? (float) $item['listprice'] : ($item['listprice'] ?? 0);
                $discountPercent = is_string($item['discount_percent']) ? (float) $item['discount_percent'] : ($item['discount_percent'] ?? 0);

                $itemTotal = $quantity * $listprice * (1 - $discountPercent / 100);
                $subtotal += $itemTotal;

                DB::connection(self::CONNECTION)->table('vtiger_inventoryproductrel')->insert([
                    'id' => $data['quoteid'],
                    'productid' => $item['productid'] ?? null,
                    'sequence_no' => $item['sequence_no'] ?? ($index + 1),
                    'quantity' => $quantity,
                    'listprice' => $listprice,
                    'discount_percent' => $discountPercent,
                    'description' => $item['description'] ?? '',
                    'comment' => $item['comment'] ?? null,
                    'incrementondel' => 0,
                ]);
            }
        }

        // Calculate totals
        $data['subtotal'] = $subtotal;
        $totalTax = $subtotal * $tax;
        $data['total'] = $subtotal + $totalTax;
        $data['compound_taxes_info'] = json_encode(['tax1' => $totalTax]);
        $data['pre_tax_total'] = $subtotal;

        // Insert quote
        DB::connection(self::CONNECTION)->table(self::TABLE)->insert($data);

        return $data['quoteid'];
    }

    public function getNextQuoteNumber(): string
    {
        $currentYearShort = date('y');
        $maxQuoteNo = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('quote_no', 'LIKE', "C-{$currentYearShort}-%")
            ->orderByRaw('CAST(SUBSTRING_INDEX(quote_no, "-", -1) AS UNSIGNED) DESC')
            ->value('quote_no');

        if (!$maxQuoteNo) {
            return 'C-' . $currentYearShort . '-00001';
        }

        try {
            $parts = explode('-', $maxQuoteNo);

            if (count($parts) !== 3 || $parts[0] !== 'C') {
                throw new \Exception('Invalid quote number format');
            }

            $yearPart = $parts[1];
            $numberPart = $parts[2];

            if ($yearPart !== $currentYearShort) {
                return 'C-' . $currentYearShort . '-00001';
            }

            $nextNumber = (int) $numberPart + 1;

            return 'C-' . $currentYearShort . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            Log::warning('Quote number format mismatch, resetting sequence', [
                'maxQuoteNo' => $maxQuoteNo,
                'error' => $e->getMessage(),
            ]);

            return 'C-' . $currentYearShort . '-00001';
        }
    }

    public function exists(int $quoteId): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('quoteid', $quoteId)
            ->exists();
    }

    public function updateQuote(int $quoteId, array $data): bool
    {
        $updated = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('quoteid', $quoteId)
            ->update($data);

        return $updated > 0;
    }

    /**
     * Duplicate an existing quote with new ID and quote number
     * 
     * @param array $baseData Base data of the quote (without quoteid or quote_no)
     * @param array $itemsData Items to insert
     * @param int $createdByUserId ID of the user creating the copy
     * @return int|null The new quoteid or null if fails
     */
    public function duplicate(array $baseData, array $itemsData, int $createdByUserId): ?int
    {
        return DB::connection('vtiger')->transaction(function () use ($baseData, $itemsData, $createdByUserId) {
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');
            $newCrmid = $maxCrmid ? $maxCrmid + 1 : 1;

            $quoteNo = $this->getNextQuoteNumber();

            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $newCrmid,
                'smownerid' => $baseData['assigned_user_id'] ?? $createdByUserId,
                'smcreatorid' => $createdByUserId,
                'setype' => 'Quotes',
                'description' => $baseData['description'] ?? null,
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);

            $subtotal = 0;
            $taxRate = self::TAX; // 0.07

            foreach ($itemsData as $index => $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                $listprice = (float) ($item['listprice'] ?? 0);
                $discountPercent = (float) ($item['discount_percent'] ?? 0);

                $netPrice = $listprice * (1 - $discountPercent / 100);
                $itemTotal = $quantity * $netPrice;
                $subtotal += $itemTotal;

                DB::connection('vtiger')->table('vtiger_inventoryproductrel')->insert([
                    'id' => $newCrmid,
                    'productid' => $item['productid'] ?? null,
                    'sequence_no' => $item['sequence_no'] ?? ($index + 1),
                    'quantity' => $quantity,
                    'listprice' => $listprice,
                    'discount_percent' => $discountPercent,
                    'description' => $item['description'] ?? null,
                    'comment' => $item['comment'] ?? null,
                    'incrementondel' => 0,
                ]);
            }

            $taxAmount = $subtotal * $taxRate;
            $total = $subtotal + $taxAmount;

            DB::connection('vtiger')->table('vtiger_quotes')->insert([
                'quoteid' => $newCrmid,
                'quote_no' => $quoteNo,
                'subject' => $baseData['subject'],
                'potentialid' => $baseData['potentialid'] ?? null,
                'accountid' => $baseData['accountid'] ?? null,
                'quotestage' => $baseData['quotestage'] ?? 'Draft',
                'validtill' => $baseData['validtill'] ?? null,
                'subtotal' => $subtotal,
                'total' => $total,
                'taxtype' => $baseData['taxtype'] ?? 'individual',
                'currency_id' => $baseData['currency_id'] ?? 1,
                'conversion_rate' => $baseData['conversion_rate'] ?? 1.000,
                'discount_percent' => $baseData['discount_percent'] ?? null,
                'discount_amount' => $baseData['discount_amount'] ?? null,
                'compound_taxes_info' => json_encode(['tax1' => $taxAmount]),
                'pre_tax_total' => $subtotal,
            ]);

            return $newCrmid;
        });
    }
}