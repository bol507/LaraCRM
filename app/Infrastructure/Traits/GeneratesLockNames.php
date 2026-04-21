<?php

namespace App\Infrastructure\Traits;

/**
 * Trait for generating consistent, unique lock names for MySQL GET_LOCK
 *
 * Provides a standardized format for application-level locks to prevent
 * race conditions during ID generation and other critical operations.
 *
 * @package App\Observers\Traits
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 */
trait GeneratesLockNames
{
    /**
     * Generate a unique lock name for a given entity type and operation
     *
     * Format: {app_name}:{entity}:{operation}:{timestamp}:{random}
     *
     * Example: "nova-crm:user:create:1713384000.123456:abc123"
     *
     * @param string $entityType The entity being operated on (e.g., 'user', 'contact')
     * @param string $operation The operation type (e.g., 'create', 'update', 'delete')
     * @param mixed $context Optional context to add uniqueness (e.g., user ID, request ID)
     * @return string Unique lock name safe for MySQL GET_LOCK
     */
    protected function getLockName(
        string $entityType,
        string $operation = 'create',
        mixed $context = null
    ): string {
        $appName = config('app.name', 'crm');
        $timestamp = microtime(true);
        $random = bin2hex(random_bytes(3)); // six charters

        $base = sprintf('%s:%s:%s:%s:%s', $appName, $entityType, $operation, $timestamp, $random);

        // add context if provided
        if ($context !== null) {
            $contextStr = is_scalar($context) ? (string) $context : md5(serialize($context));
            $base .= ':' . substr($contextStr, 0, 12); // Limitar longitud
        }

        // MySQL GET_LOCK requires a 64-character string
        return substr($base, 0, 64);
    }

    /**
     * Generate a lock name specifically for ID generation operations
     *
     * @param string $table Target table name (e.g., 'vtiger_users')
     * @param mixed $context Optional context for uniqueness
     * @return string Lock name formatted for ID generation
     */
    protected function getIdGenerationLockName(
        string $table,
        mixed $context = null
    ): string {
        $entityType = preg_replace('/^vtiger_/', '', $table);
        return $this->getLockName($entityType, 'id_gen', $context);
    }
}