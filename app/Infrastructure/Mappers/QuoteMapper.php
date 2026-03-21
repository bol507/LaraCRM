<?php

namespace App\Infrastructure\Mappers;

use App\Domain\Entities\Quote;
use InvalidArgumentException;

/**
 * Quote Mapper
 * 
 * Converts between Vtiger database representations and domain Quote entities.
 * Supports both raw database rows and domain entity transformations.
 * 
 * @package App\Infrastructure\Mappers
 */
class QuoteMapper
{
    /**
     * Map raw database row to domain Quote entity.
     * 
     * @param object $row Raw database row from joined query
     * @return Quote Domain entity with all properties mapped
     * @throws InvalidArgumentException If required fields are missing
     */
    public static function fromDatabaseRow(object $row): Quote
    {
        // Validate required fields
        self::validateRequiredFields($row);

        return new Quote(
            // Required fields
            quoteid: (int) $row->quoteid,
            quoteno: $row->quote_no,  // ← BD: quote_no → Entidad: quoteno
            subject: $row->subject,
            quote_stage: $row->quote_stage ?? 'Draft',
            
            // Optional fields - usar ?? null para manejar valores null de la BD
            accountid: isset($row->accountid) ? (int) $row->accountid : null,
            potentialid: isset($row->potentialid) ? (int) $row->potentialid : null,
            validtill: $row->validtill ?? null,
            subtotal: isset($row->subtotal) ? (float) $row->subtotal : null,
            total: isset($row->total) ? (float) $row->total : null,
            currency_id: isset($row->currency_id) ? (int) $row->currency_id : null,
            
            // Denormalized account fields
            account_name: $row->account_name ?? null,
            account_no: $row->account_no ?? null,
            
            // Assigned user
            assigned_user_id: isset($row->assigned_user_id) ? (int) $row->assigned_user_id : null,
            assigned_user_name: $row->assigned_user_name ?? null,
            
            // Additional quote fields
            description: $row->description ?? null,
            discount_percent: isset($row->discount_percent) ? (float) $row->discount_percent : null,
            
            // Line items - se poblarán después desde vtiger_inventorybuffer o similar
            items: [],  // ← Valor por defecto, se puede poblar después
            
            // Audit fields
            createdtime: $row->createdtime ?? null,
            modifiedtime: $row->modifiedtime ?? null,
            isActive: ($row->deleted ?? 0) === 0,
        );
    }

    /**
     * Map collection of database rows to Quote entities.
     * 
     * @param array<object> $rows Array of raw database rows
     * @return array<Quote> Array of mapped Quote entities
     */
    public static function fromDatabaseRows(array $rows): array
    {
        return array_map(fn($row) => self::fromDatabaseRow($row), $rows);
    }

    /**
     * Map domain Quote entity to persistence arrays.
     * 
     * @param Quote $quote Domain entity to map
     * @param bool $isUpdate Whether this is an update operation
     * @return array{quotes: array, crmentity: array, inventory: array}
     */
    public static function toPersistence(Quote $quote, bool $isUpdate = false): array
    {
        $now = date('Y-m-d H:i:s');
        
        return [
            'quotes' => array_filter([
                'quoteid' => $quote->quoteid,
                'quote_no' => $quote->quoteno,  // ← Entidad: quoteno → BD: quote_no
                'subject' => $quote->subject,
                'accountid' => $quote->accountid,
                'potentialid' => $quote->potentialid,
                'quote_stage' => $quote->quote_stage,
                'validtill' => $quote->validtill,
                'subtotal' => $quote->subtotal,
                'total' => $quote->total,
                'currency_id' => $quote->currency_id,
                'description' => $quote->description,
                'discount_percent' => $quote->discount_percent,
            ], fn($v) => $v !== null),
            
            'crmentity' => array_filter([
                'crmid' => $quote->quoteid,
                'smcreatorid' => $isUpdate ? null : 1,
                'smownerid' => $quote->assigned_user_id ?? 1,
                'modifiedby' => $quote->assigned_user_id ?? 1,
                'setype' => 'Quotes',
                'createdtime' => $isUpdate ? null : $now,
                'modifiedtime' => $now,
                'deleted' => 0,
                'version' => 0,
                'presence' => 1,
            ], fn($v) => $v !== null),
            
            // Los items se manejan en tablas separadas (vtiger_inventorybuffer, etc.)
            'inventory' => $quote->items,
        ];
    }

    /**
     * Validate that all required fields are present in the database row.
     * 
     * @param object $row Database row to validate
     * @return void
     * @throws InvalidArgumentException If required fields are missing
     */
    private static function validateRequiredFields(object $row): void
    {
        $requiredFields = [
            'quoteid',
            'quote_no',  // ← Nombre en la BD
            'subject',
            'quote_stage',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($row->$field)) {
                throw new InvalidArgumentException(
                    "Required field '{$field}' is missing from database row"
                );
            }
        }
    }
}