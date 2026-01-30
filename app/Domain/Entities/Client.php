<?php

namespace App\Domain\Entities;

class Client
{
     public function __construct(
        public readonly int $accountid,
        public readonly string $account_no,
        public readonly string $accountname,
        public readonly int $parentid,
        public readonly ?string $account_type,
        public readonly ?string $industry,
        public readonly ?float $annualrevenue,
        public readonly ?string $rating,
        public readonly ?string $ownership,
        public readonly ?string $siccode,
        public readonly ?string $tickersymbol,
        public readonly ?string $phone,
        public readonly ?string $otherphone,
        public readonly ?string $email1,
        public readonly ?string $email2,
        public readonly ?string $website,
        public readonly ?string $fax,
        public readonly ?int $employees,
        public readonly string $emailoptout, // "0" o "1"
        public readonly string $notify_owner, // "0" o "1"
        public readonly string $isconvertedfromlead, // "0" o "1"
        public readonly ?string $tags,
        public readonly bool $isActive,
        // Dirección de facturación
        public readonly ?string $bill_street,
        public readonly ?string $bill_city,
        public readonly ?string $bill_state,
        public readonly ?string $bill_code,
        public readonly ?string $bill_country,
        public readonly ?string $bill_pobox,
        
        // Dirección de envío
        public readonly ?string $ship_street,
        public readonly ?string $ship_city,
        public readonly ?string $ship_state,
        public readonly ?string $ship_code,
        public readonly ?string $ship_country,
        public readonly ?string $ship_pobox,
    ) {}
}