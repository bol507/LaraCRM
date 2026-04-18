<?php

namespace App\Application\DTOs\Vendor;

class CreateVendorRequest
{
    public function __construct(
        public readonly string $vendorname,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $category,
        public readonly ?string $website,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postalcode,
        public readonly ?string $country,
        public readonly ?string $description,
        public readonly int $assigned_user_id,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            vendorname: trim($data['vendorname']),
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            category: $data['category'] ?? null,
            website: $data['website'] ?? null,
            street: $data['street'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            postalcode: $data['postalcode'] ?? null,
            country: $data['country'] ?? null,
            description: $data['description'] ?? null,
            assigned_user_id: (int) ($data['assigned_user_id'] ?? 1),
        );
    }
}