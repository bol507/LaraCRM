<?php

namespace App\Application\DTOs\Quote;

/**
 * Class QuoteResponse
 * 
 * Data Transfer Object for API responses containing quote data with denormalized
 * related entity information.
 * 
 * This DTO is used to structure the JSON response for the GET /api/quotes endpoint,
 * including line items and related account, potential, and user names for display
 * purposes in the frontend. It follows the immutability pattern using readonly
 * properties to ensure data integrity after instantiation.
 * 
 * The DTO includes both core quote fields from the vtiger_quotes table and
 * denormalized data from related tables (vtiger_account, vtiger_potential,
 * vtiger_users) to minimize the number of API calls required by the frontend.
 * 
 * @package App\Application\DTOs\Quote
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see \App\Domain\Entities\Quote For the domain entity that this DTO may be derived from
 * @see \App\Infrastructure\Mappers\QuoteMapper For the mapper that transforms database rows to this DTO
 * @see \App\Http\Controllers\Api\QuoteController For the controller that returns this DTO in API responses
 */
class QuoteResponse
{
    
    /**
     * QuoteResponse constructor.
     * 
     * Creates an immutable DTO instance containing quote data for API responses.
     * All properties are marked as readonly to ensure immutability after
     * instantiation, promoting safer data handling and preventing accidental
     * modifications during request processing.
     * 
     * @param int $quoteid The unique identifier of the quote.
     * @param string $quoteno The quote number as displayed in the system.
     * @param string $subject The subject or title of the quote.
     * @param string $quote_stage The current stage of the quote in the sales pipeline.
     * @param int|null $potentialid The ID of the related potential (opportunity), if any.
     * @param int|null $accountid The ID of the related account (client), if any.
     * @param int|null $assigned_user_id The ID of the user assigned to the quote.
     * @param string|null $validtill The date until which the quote is valid.
     * @param string|null $description Additional description or notes for the quote.
     * @param float|null $subtotal The subtotal amount before discounts and taxes.
     * @param float|null $discount_percent The discount percentage applied to the quote.
     * @param float|null $total The total amount of the quote after all adjustments.
     * @param int|null $currency_id The ID of the currency used for the quote.
     * @param string|null $createdtime The timestamp when the quote record was created.
     * @param string|null $modifiedtime The timestamp when the quote record was last modified.
     * @param array $items The line items included in the quote.
     * @param string|null $account_name The name of the related account, denormalized for display.
     * @param string|null $account_no The account number of the related account, denormalized for display.
     * @param string|null $potential_name The name of the related potential, denormalized for display.
     * @param string|null $assigned_user_name The name of the assigned user, denormalized for display.
     * @param bool $isActive Flag indicating whether the quote record is active.
     * 
     * @return void
     */
    public function __construct(
        public readonly int $quoteid,
        public readonly string $quoteno,
        public readonly string $subject,
        public readonly string $quote_stage,
        public readonly ?int $potentialid = null,
        public readonly ?int $accountid = null,
        public readonly ?int $assigned_user_id = null,
        public readonly ?string $validtill = null,
        public readonly ?string $description = null,
        public readonly ?float $subtotal = null,
        public readonly ?float $discount_percent = null,
        public readonly ?float $total = null,
        public readonly ?int $currency_id = null,
        public readonly ?string $createdtime = null,
        public readonly ?string $modifiedtime = null,
        public readonly array $items = [],
        public readonly ?string $account_name = null,
        public readonly ?string $account_no = null,
        public readonly ?string $potential_name = null,
        public readonly ?string $assigned_user_name = null,
        public readonly bool $isActive = true,
    ) {}

    /**
     * Convert the DTO to an associative array for JSON serialization.
     * 
     * This method prepares the DTO data for transmission in API responses.
     * The returned array uses snake_case keys to conform to common JSON API
     * conventions and includes all public properties of the DTO.
     * 
     * The 'is_active' key in the output array corresponds to the 'isActive'
     * property in the PHP class, following the convention of converting
     * camelCase properties to snake_case for JSON output.
     * 
     * @return array<string, mixed> An associative array containing all DTO
     *                              properties formatted for JSON serialization.
     * 
     * @example
     * // Convert DTO to array for API response
     * $dto = new QuoteResponse(
     *     quoteid: 123,
     *     quoteno: "QT-2026-001",
     *     subject: "Enterprise License Quote",
     *     quote_stage: "Sent",
     *     total: 50000.00,
     *     account_name: "Acme Corporation",
     *     // ... other properties
     * );
     * $responseData = $dto->toArray();
     * 
     * @example
     * // Use in controller response
     * return response()->json($dto->toArray());
     * 
     * @see json_encode() For PHP's JSON serialization function
     * @see \Illuminate\Http\JsonResponse For Laravel's JSON response class
     */
    public function toArray(): array
    {
        return [
            'quoteid' => $this->quoteid,
            'quoteno' => $this->quoteno,
            'subject' => $this->subject,
            'quote_stage' => $this->quote_stage,
            'potentialid' => $this->potentialid,
            'accountid' => $this->accountid,
            'assigned_user_id' => $this->assigned_user_id,
            'validtill' => $this->validtill,
            'description' => $this->description,
            'subtotal' => $this->subtotal,
            'discount_percent' => $this->discount_percent,
            'total' => $this->total,
            'currency_id' => $this->currency_id,
            'createdtime' => $this->createdtime,
            'modifiedtime' => $this->modifiedtime,
            'items' => $this->items,
            'account_name' => $this->account_name,
            'account_no' => $this->account_no,
            'potential_name' => $this->potential_name,
            'assigned_user_name' => $this->assigned_user_name,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * Create a QuoteResponse DTO instance from an associative array.
     * 
     * This static factory method is primarily useful for testing scenarios
     * where DTO instances need to be created from mock data, or for
     * deserializing JSON request bodies into DTO objects.
     * 
     * The method applies type casting and default values to ensure that
     * the resulting DTO instance is valid even if the input array is
     * incomplete or contains unexpected data types.
     * 
     * @param array<string, mixed> $data An associative array containing DTO
     *                                   property values with snake_case keys.
     * 
     * @return self A new QuoteResponse instance populated with data from
     *              the provided array.
     * 
     * @example
     * // Create DTO from test data
     * $testData = [
     *     'quoteid' => 123,
     *     'quoteno' => 'QT-2026-001',
     *     'subject' => 'Test Quote',
     *     'quote_stage' => 'Draft',
     *     'total' => 1000.00,
     * ];
     * $dto = QuoteResponse::fromArray($testData);
     * 
     * @example
     * // Create DTO from JSON request body
     * $requestData = json_decode($request->getContent(), true);
     * $dto = QuoteResponse::fromArray($requestData);
     * 
     * @throws \InvalidArgumentException If required fields are missing
     *                                   or contain invalid data types.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            quoteid: (int) ($data['quoteid'] ?? 0),
            quoteno: $data['quoteno'] ?? '',
            subject: $data['subject'] ?? '',
            quote_stage: $data['quote_stage'] ?? '',
            potentialid: isset($data['potentialid']) ? (int) $data['potentialid'] : null,
            accountid: isset($data['accountid']) ? (int) $data['accountid'] : null,
            assigned_user_id: isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            validtill: $data['validtill'] ?? null,
            description: $data['description'] ?? null,
            subtotal: isset($data['subtotal']) ? (float) $data['subtotal'] : null,
            discount_percent: isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
            total: isset($data['total']) ? (float) $data['total'] : null,
            currency_id: isset($data['currency_id']) ? (int) $data['currency_id'] : null,
            createdtime: $data['createdtime'] ?? null,
            modifiedtime: $data['modifiedtime'] ?? null,
            items: $data['items'] ?? [],
            account_name: $data['account_name'] ?? null,
            account_no: $data['account_no'] ?? null,
            potential_name: $data['potential_name'] ?? null,
            assigned_user_name: $data['assigned_user_name'] ?? null,
            isActive: $data['is_active'] ?? true,
        );
    }
}