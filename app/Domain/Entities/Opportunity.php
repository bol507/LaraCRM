<?php

namespace App\Domain\Entities;

/**
 * Class Opportunity
 * 
 * Domain entity representing a sales opportunity (potential) in Vtiger CRM.
 * 
 * This entity encapsulates all data related to a sales opportunity or potential
 * deal in the CRM system. It maps to the vtiger_potential table joined with
 * vtiger_crmentity for audit information and optionally with vtiger_account
 * for denormalized client information.
 * 
 * The entity follows the immutability pattern using readonly properties with
 * constructor property promotion, ensuring that once created, opportunity data
 * cannot be modified. This promotes safer data handling, predictable behavior,
 * and easier reasoning about data flow throughout the application.
 * 
 * Key characteristics:
 * - Immutable: All properties are readonly after instantiation via constructor promotion
 * - Nullable fields: Optional database fields are marked as nullable with default null
 * - Denormalized data: Includes related account and user names for display efficiency
 * - Soft delete support: is_active flag derived from vtiger_crmentity.deleted
 * - Sales pipeline: sales_stage and probability fields for deal tracking
 * 
 * Database mapping:
 * - Primary table: vtiger_potential (potentialid is primary key)
 * - Audit join: vtiger_crmentity ON potentialid = crmid
 * - Optional join: vtiger_account ON related_to = accountid
 * - Optional join: vtiger_users ON smownerid = id
 * 
 * @package App\Domain\Entities
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see \App\Application\Repositories\OpportunityRepositoryInterface For persistence operations
 * @see \App\Infrastructure\Mappers\OpportunityMapper For database row to entity mapping
 * @see \App\Application\DTOs\CreateOpportunityRequest For creation request DTO
 * @see \App\Application\DTOs\UpdateOpportunityRequest For update request DTO
 */
class Opportunity
{
    

    /**
     * Opportunity constructor.
     * 
     * Creates an immutable Opportunity entity instance with all opportunity data
     * from the Vtiger CRM database. All properties are declared and initialized
     * via constructor property promotion with readonly modifier, ensuring
     * immutability after instantiation without requiring separate property
     * declarations.
     * 
     * The entity is designed to be created by the repository layer after fetching
     * and joining data from vtiger_potential, vtiger_crmentity, and optionally
     * vtiger_account and vtiger_users tables. Application code should not
     * instantiate this class directly but should use repository methods instead.
     * 
     * @param int $potentialid The unique identifier of the opportunity.
     * @param string $potential_no The opportunity number as displayed in the CRM system.
     * @param string $potentialname The name or title of the sales opportunity.
     * @param float|null $amount The estimated monetary value of the opportunity.
     * @param string|null $closingdate The expected closing date of the opportunity.
     * @param string $sales_stage The current stage of the opportunity in the sales pipeline.
     * @param int|null $probability The probability percentage of winning the opportunity.
     * @param int|null $related_to The ID of the related account for this opportunity.
     * @param string|null $related_to_name The name of the related account, denormalized for display.
     * @param int|null $assigned_user_id The ID of the user assigned to manage this opportunity.
     * @param string|null $assigned_user_name The name of the assigned user, denormalized for display.
     * @param string|null $description Additional description or notes about the opportunity.
     * @param bool $is_active Flag indicating whether the opportunity is active (not deleted).
     * 
     * @return void
     */
    public function __construct(
        public readonly int $potentialid,
        public readonly string $potential_no,
        public readonly string $potentialname, 
        public readonly ?float $amount = null,
        public readonly ?string $closingdate = null,
        public readonly string $sales_stage,
        public readonly ?int $probability = null,
        public readonly ?int $related_to = null, 
        public readonly ?string $related_to_name = null,
        public readonly ?int $assigned_user_id = null, 
        public readonly ?string $assigned_user_name = null,
        public readonly ?string $description = null,
        public readonly bool $is_active = true
    ) {}

    /**
     * Check if the opportunity has an associated account.
     * 
     * Determines whether this opportunity is linked to a client account by
     * checking if the related_to property is populated. Useful for conditional
     * display of account information or filtering opportunities by account
     * association status.
     * 
     * @return bool True if the opportunity has an associated account, false otherwise.
     * 
     * @example
     * // Conditionally display account information
     * if ($opportunity->hasAccount()) {
     *     echo "Client: {$opportunity->related_to_name}";
     * }
     * 
     * @example
     * // Filter opportunities with accounts
     * $withAccounts = array_filter($opportunities, fn($o) => $o->hasAccount());
     */
    public function hasAccount(): bool
    {
        return $this->related_to !== null;
    }

    /**
     * Check if the opportunity is assigned to a specific user.
     * 
     * Determines whether this opportunity has an assigned owner by checking
     * if the assigned_user_id property is populated. Useful for permission
     * checks, ownership-based filtering, and conditional display of user
     * information.
     * 
     * @return bool True if the opportunity is assigned to a user, false otherwise.
     * 
     * @example
     * // Check ownership before allowing edit
     * if ($opportunity->isAssigned() && $opportunity->assigned_user_id !== $currentUserId) {
     *     throw new AuthorizationException('Not authorized to edit this opportunity');
     * }
     * 
     * @example
     * // Filter assigned opportunities
     * $assigned = array_filter($opportunities, fn($o) => $o->isAssigned());
     */
    public function isAssigned(): bool
    {
        return $this->assigned_user_id !== null;
    }

    /**
     * Check if the opportunity is in a closed state.
     * 
     * Determines whether the opportunity has reached a terminal stage in the
     * sales pipeline by checking if the sales_stage is "Closed Won" or
     * "Closed Lost". Useful for filtering closed deals, calculating win rates,
     * and excluding closed opportunities from active pipeline views.
     * 
     * @return bool True if the opportunity is closed (won or lost), false otherwise.
     * 
     * @example
     * // Exclude closed opportunities from active pipeline
     * $active = array_filter($opportunities, fn($o) => !$o->isClosed());
     * 
     * @example
     * // Calculate win rate from closed opportunities
     * $closed = array_filter($opportunities, fn($o) => $o->isClosed());
     * $won = array_filter($closed, fn($o) => $o->sales_stage === 'Closed Won');
     * $winRate = count($closed) > 0 ? count($won) / count($closed) : 0;
     */
    public function isClosed(): bool
    {
        return in_array($this->sales_stage, ['Closed Won', 'Closed Lost'], true);
    }

    /**
     * Check if the opportunity has been won.
     * 
     * Determines whether the opportunity has successfully closed by checking
     * if the sales_stage is exactly "Closed Won". Useful for revenue recognition,
     * commission calculations, and won-deal reporting.
     * 
     * @return bool True if the opportunity is won, false otherwise.
     * 
     * @example
     * // Calculate total won revenue
     * $wonRevenue = array_sum(
     *     array_map(
     *         fn($o) => $o->amount ?? 0,
     *         array_filter($opportunities, fn($o) => $o->isWon())
     *     )
     * );
     * 
     * @example
     * // Display won badge in UI
     * if ($opportunity->isWon()) {
     *     echo '<span class="badge badge-success">Won</span>';
     * }
     */
    public function isWon(): bool
    {
        return $this->sales_stage === 'Closed Won';
    }

    /**
     * Check if the opportunity has been lost.
     * 
     * Determines whether the opportunity has been lost by checking if the
     * sales_stage is exactly "Closed Lost". Useful for loss analysis, reason
     * tracking, and improving sales processes.
     * 
     * @return bool True if the opportunity is lost, false otherwise.
     * 
     * @example
     * // Analyze lost opportunities
     * $lost = array_filter($opportunities, fn($o) => $o->isLost());
     * foreach ($lost as $opportunity) {
     *     // Log or analyze reason for loss
     * }
     * 
     * @example
     * // Display lost badge in UI
     * if ($opportunity->isLost()) {
     *     echo '<span class="badge badge-danger">Lost</span>';
     * }
     */
    public function isLost(): bool
    {
        return $this->sales_stage === 'Closed Lost';
    }

    /**
     * Get the weighted value of the opportunity.
     * 
     * Calculates the expected revenue by multiplying the amount by the
     * probability percentage. Returns null if either amount or probability
     * is not set. Used for weighted pipeline calculations and more accurate
     * revenue forecasting.
     * 
     * @return float|null The weighted value (amount * probability / 100),
     *                    or null if calculation cannot be performed.
     * 
     * @example
     * // Calculate total weighted pipeline
     * $weightedTotal = array_sum(
     *     array_map(
     *         fn($o) => $o->getWeightedValue() ?? 0,
     *         $opportunities
     *     )
     * );
     * 
     * @example
     * // Display weighted value in UI
     * $weighted = $opportunity->getWeightedValue();
     * if ($weighted !== null) {
     *     echo 'Expected: $' . number_format($weighted, 2);
     * }
     */
    public function getWeightedValue(): ?float
    {
        if ($this->amount === null || $this->probability === null) {
            return null;
        }
        return $this->amount * ($this->probability / 100);
    }

    /**
     * Convert the entity to an array for JSON serialization.
     * 
     * Prepares the opportunity data for transmission in API responses.
     * The returned array uses snake_case keys to conform to common JSON
     * API conventions and includes all public properties of the entity.
     * 
     * @return array<string, mixed> An associative array containing all
     *                              entity properties formatted for JSON.
     * 
     * @example
     * // Convert to array for API response
     * $responseData = $opportunity->toArray();
     * 
     * @example
     * // Use in controller
     * return response()->json($opportunity->toArray());
     * 
     * @example
     * // Response structure
     * // Returns:
     * // [
     * //     'potentialid' => 456,
     * //     'potential_no' => 'POT-2026-001',
     * //     'potentialname' => 'Enterprise License Deal',
     * //     'amount' => 50000.00,
     * //     'closingdate' => '2026-06-30',
     * //     'sales_stage' => 'Proposal',
     * //     'probability' => 60,
     * //     'related_to' => 123,
     * //     'related_to_name' => 'Acme Corporation',
     * //     'assigned_user_id' => 5,
     * //     'assigned_user_name' => 'John Doe',
     * //     'description' => 'Annual enterprise license renewal',
     * //     'is_active' => true,
     * //     'weighted_value' => 30000.00
     * // ]
     */
    public function toArray(): array
    {
        return [
            'potentialid' => $this->potentialid,
            'potential_no' => $this->potential_no,
            'potentialname' => $this->potentialname,
            'amount' => $this->amount,
            'closingdate' => $this->closingdate,
            'sales_stage' => $this->sales_stage,
            'probability' => $this->probability,
            'related_to' => $this->related_to,
            'related_to_name' => $this->related_to_name,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_user_name' => $this->assigned_user_name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'weighted_value' => $this->getWeightedValue(),
        ];
    }
}