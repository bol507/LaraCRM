<?php

namespace App\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Repository for vtiger_accountbillads - Billing address table
 *
 * Handles upsert operations for billing addresses.
 */
class BillingAddressRepository
{
    private const TABLE = 'vtiger_accountbillads';

    private const CONNECTION = 'vtiger';

    public function upsert(int $accountId, array $data): bool
    {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('accountid must be positive');
        }

        $existing = $this->findByAccountId($accountId);

        $addressData = $this->prepareData($data);

        if ($existing) {
            $affected = DB::connection(self::CONNECTION)
                ->table(self::TABLE)
                ->where('accountaddressid', $accountId)
                ->update($addressData);
        } else {
            $addressData['accountaddressid'] = $accountId;
            DB::connection(self::CONNECTION)
                ->table(self::TABLE)
                ->insert($addressData);
            $affected = 1;
        }

        return $affected > 0;
    }

    public function findByAccountId(int $accountId): ?array
    {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('accountid must be positive');
        }

        $row = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('accountaddressid', $accountId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function delete(int $accountId): bool
    {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('accountid must be positive');
        }

        $affected = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('accountaddressid', $accountId)
            ->delete();

        return $affected > 0;
    }

    private function prepareData(array $data): array
    {
        return [
            'bill_street' => $data['bill_street'] ?? null,
            'bill_city' => $data['bill_city'] ?? null,
            'bill_state' => $data['bill_state'] ?? null,
            'bill_code' => $data['bill_code'] ?? null,
            'bill_country' => $data['bill_country'] ?? null,
            'bill_pobox' => $data['bill_pobox'] ?? null,
        ];
    }
}
