<?php

namespace App\Domain\Entities;

/**
 * Quote entity representing a quote in Vtiger CRM.
 * 
 * Maps to vtiger_quotes table joined with vtiger_crmentity 
 * and vtiger_account for comprehensive quote data.
 */
class Quote
{
    public function __construct(
        // Required fields (never null in database)
        public readonly int $quoteid,
        public readonly string $quoteno,  // ← Nota: sin guión bajo para coincidir con estilo PHP
        public readonly string $subject,
        public readonly string $quote_stage,
        
        // Optional fields (can be null in database)
        public readonly ?int $accountid = null,  // ← Ahora es nullable
        public readonly ?int $potentialid = null,
        public readonly ?string $validtill = null,
        public readonly ?float $subtotal = null,  // ← Ahora es nullable
        public readonly ?float $total = null,     // ← Ahora es nullable
        public readonly ?int $currency_id = null, // ← Agregado
        
        // Denormalized account fields (from JOIN with vtiger_account)
        public readonly ?string $account_name = null,
        public readonly ?string $account_no = null,
        
        // Assigned user (from JOIN with vtiger_users)
        public readonly ?int $assigned_user_id = null,
        public readonly ?string $assigned_user_name = null,
        
        // Additional quote fields
        public readonly ?string $description = null,
        public readonly ?float $discount_percent = null,
        
        // Line items (populated separately, not from main query)
        public readonly array $items = [],  // ← Ahora tiene valor por defecto
        
        // Audit fields from vtiger_crmentity
        public readonly ?string $createdtime = null,
        public readonly ?string $modifiedtime = null,
        public readonly bool $isActive = true,
    ) {}
}