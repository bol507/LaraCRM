<?php

namespace App\Infrastructure\Repositories\Core;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Repository for generating unique IDs with application-level locking
 *
 * Follows Vtiger's legacy pattern for ID generation while ensuring thread safety
 */
class IdGeneratorRepository
{
    public const MAX_RETRIES = 3;

    public const LOCK_TIMEOUT_SECONDS = 10;

    public const RETRY_DELAY_MICROSECONDS = 100_000; // 100ms

    public function __construct(
        private readonly string $connection = 'vtiger'
    ) {}

    /**
     * Generate a new unique ID for a table using GET_LOCK
     *
     * @param  string  $table  Table name (e.g., 'vtiger_activity')
     * @param  string  $column  Primary key column (e.g., 'activityid')
     * @param  string  $lockName  Unique lock identifier
     * @return int The new unique ID
     *
     * @throws RuntimeException If lock cannot be acquired or ID generation fails
     */
    public function generateNextId(
        string $table,
        string $column,
        string $lockName
    ): int {
        $attempt = 0;
        $connection = DB::connection($this->connection);

        while ($attempt < self::MAX_RETRIES) {
            try {
                // Acquire application-level lock
                if (! $this->acquireLock($connection, $lockName)) {
                    throw new RuntimeException(
                        "Failed to acquire lock '{$lockName}' after ".self::LOCK_TIMEOUT_SECONDS.' seconds'
                    );
                }

                try {
                    // Get current max ID from vtiger_crmentity (source of truth for all entity IDs)
                    $maxId = $connection->table('vtiger_crmentity')->max('crmid');
                    $newId = (int) ($maxId ?? 0) + 1;

                    // Defense in depth: verify ID doesn't exist
                    if ($this->idExists($connection, $table, $column, $newId)) {
                        $newId = $this->findNextAvailableId($connection, $table, $column, $newId);
                    }

                    return $newId;

                } finally {
                    // Always release lock
                    $this->releaseLock($connection, $lockName);
                }

            } catch (QueryException $e) {
                $attempt++;

                // Retry on constraint errors
                if ($attempt < self::MAX_RETRIES && $this->isConstraintError($e)) {
                    usleep(self::RETRY_DELAY_MICROSECONDS);

                    continue;
                }

                throw new RuntimeException(
                    sprintf('Failed to generate ID after %d attempt(s): %s', $attempt, $e->getMessage()),
                    previous: $e
                );
            }
        }

        throw new RuntimeException(
            sprintf('Failed to generate ID after %d retry attempts', self::MAX_RETRIES)
        );
    }

    /**
     * Acquire MySQL GET_LOCK with timeout
     */
    private function acquireLock(Connection $connection, string $lockName): bool
    {
        $result = $connection->selectOne(
            'SELECT GET_LOCK(?, ?) as acquired',
            [$lockName, self::LOCK_TIMEOUT_SECONDS]
        );

        return $result && $result->acquired == 1;
    }

    /**
     * Release MySQL GET_LOCK
     */
    private function releaseLock(Connection $connection, string $lockName): void
    {
        try {
            $connection->statement('SELECT RELEASE_LOCK(?)', [$lockName]);
        } catch (\Exception $e) {
            // Log but don't throw - lock will auto-release on connection close
            Log::warning("Failed to release lock '{$lockName}': ".$e->getMessage());
        }
    }

    /**
     * Check if an ID already exists in the table
     */
    private function idExists(
        Connection $connection,
        string $table,
        string $column,
        int $id
    ): bool {
        return $connection->table($table)
            ->where($column, $id)
            ->exists();
    }

    /**
     * Find next available ID starting from a given value
     */
    private function findNextAvailableId(
        Connection $connection,
        string $table,
        string $column,
        int $startId
    ): int {
        $candidate = $startId;
        $maxAttempts = 100; // Prevent infinite loop
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            if (! $this->idExists($connection, $table, $column, $candidate)) {
                return $candidate;
            }
            $candidate++;
            $attempts++;
        }

        throw new RuntimeException(
            "Could not find available ID after {$maxAttempts} attempts starting from {$startId}"
        );
    }

    /**
     * Check if exception is due to constraint violation
     */
    private function isConstraintError(QueryException $e): bool
    {
        $code = $e->errorInfo[1] ?? 0;

        // MySQL error codes: 1062 = duplicate entry, 1452 = foreign key
        return in_array($code, [1062, 1452, 1048]);
    }
}
