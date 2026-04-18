<?php

namespace App\Domain\Entities;

class Vendor
{
    public function __construct(
        public readonly int $vendorid,
        public readonly string $vendorname,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $category,
        public readonly ?string $website,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $code, // postalcode en Vtiger
        public readonly ?string $country,
        public readonly ?string $description,
        public readonly int $assigned_user_id,
        public readonly ?string $assigned_user_name,
        public readonly string $createdtime,
        public readonly ?string $modifiedtime,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->vendorid,
            'vendorname' => $this->vendorname,
            'email' => $this->email,
            'phone' => $this->phone,
            'category' => $this->category,
            'website' => $this->website,
            'address' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postalcode' => $this->code,
            'country' => $this->country,
            'description' => $this->description,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_user_name' => $this->assigned_user_name,
            'createdtime' => $this->createdtime,
            'modifiedtime' => $this->modifiedtime,
        ];
    }
}