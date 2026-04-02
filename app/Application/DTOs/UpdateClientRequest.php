<?php

namespace App\Application\DTOs;

class UpdateClientRequest
{
    public function __construct(
        public readonly int $id,
        public readonly string $accountname,
        public readonly string $account_no,
        public readonly ?string $account_type = null,
        public readonly ?string $industry = null,
        public readonly ?float $annualrevenue = null,
        public readonly ?string $rating = null,
        public readonly ?string $ownership = null,
        public readonly ?string $siccode = null,
        public readonly ?string $tickersymbol = null,
        public readonly ?string $phone = null,
        public readonly ?string $otherphone = null,
        public readonly ?string $email1 = null,
        public readonly ?string $email2 = null,
        public readonly ?string $website = null,
        public readonly ?string $fax = null,
        public readonly ?int $employees = null,
        public readonly string $emailoptout = '0',
        public readonly string $notify_owner = '0',
        public readonly string $isconvertedfromlead = '0',
        public readonly ?string $tags = null,
        // Dirección de facturación
        public readonly ?string $bill_street = null,
        public readonly ?string $bill_city = null,
        public readonly ?string $bill_state = null,
        public readonly ?string $bill_code = null,
        public readonly ?string $bill_country = null,
        public readonly ?string $bill_pobox = null,
        // Dirección de envío
        public readonly ?string $ship_street = null,
        public readonly ?string $ship_city = null,
        public readonly ?string $ship_state = null,
        public readonly ?string $ship_code = null,
        public readonly ?string $ship_country = null,
        public readonly ?string $ship_pobox = null,

        // Description (stored in vtiger_crmentity)
        public readonly ?string $description = null,
    ) {}
}
