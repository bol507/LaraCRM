<?php

namespace App\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Repository for vtiger_accountshipads - Shipping address table
 *
 * Handles upsert operations for shipping addresses.
 */
class ShippingAddressRepository
{
    private const TABLE = 'vtiger_accountshipads';

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
            'ship_street' => $data['ship_street'] ?? null,
            'ship_city' => $data['ship_city'] ?? null,
            'ship_state' => $data['ship_state'] ?? null,
            'ship_code' => $data['ship_code'] ?? null,
            'ship_country' => $data['ship_country'] ?? null,
            'ship_pobox' => $data['ship_pobox'] ?? null,
        ];
    }
}
