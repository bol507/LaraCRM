<?php

namespace App\Application\Repositories;

/**
 * Interface GeneralConditionsRepositoryInterface
 * 
 * Defines the contract for retrieving general conditions and terms data in the CRM system.
 * 
 * This interface abstracts the data access layer for general conditions, which are
 * predefined text templates used in documents such as quotes, invoices, contracts,
 * and proposals. These conditions typically contain legal terms, payment conditions,
 * delivery terms, or other standardized clauses that apply to business transactions.
 * 
 * The repository pattern provides a clean separation between domain logic and data
 * access logic, promoting testability, maintainability, and adherence to the
 * Dependency Inversion Principle. Implementations may retrieve conditions from
 * database tables, configuration files, or external content management systems.
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see \App\Infrastructure\Repositories\Vtiger\VtigerGeneralConditionsRepository For the Vtiger-specific implementation
 */
interface GeneralConditionsRepositoryInterface
{
    /**
     * Retrieve general conditions content by type.
     * 
     * This method fetches predefined conditions text based on the specified type.
     * General conditions are standardized text templates used in business documents
     * such as quotes, invoices, contracts, and proposals. Each type represents a
     * different category of conditions (e.g., "payment", "delivery", "warranty",
     * "privacy", "terms_of_service").
     * 
     * The method returns the raw HTML or plain text content ready for inclusion
     * in generated documents. The content may include placeholders or variables
     * that should be replaced by the calling code with dynamic values.
     * 
     * @param string $type The type identifier for the conditions to retrieve.
     *                     Common values include: "payment", "delivery", "warranty",
     *                     "privacy", "terms_of_service", "cancellation", or custom
     *                     types configured in the system. The type is case-sensitive.
     * 
     * @return string|null The conditions content as a string if found, or null if
     *                     no conditions exist for the specified type. Returns null
     *                     rather than an empty string to distinguish between
     *                     "no conditions defined" and "empty conditions".
     * 
     * @throws \RuntimeException If the database query fails or the conditions
     *                           storage system is unavailable.
     * 
     * @example
     * // Get payment conditions for a quote
     * $conditions = $repository->getConditionsByType('payment');
     * if ($conditions !== null) {
     *     $quote->setTermsConditions($conditions);
     * }
     * 
     * @example
     * // Get multiple condition types for a contract
     * $paymentTerms = $repository->getConditionsByType('payment');
     * $deliveryTerms = $repository->getConditionsByType('delivery');
     * $warrantyTerms = $repository->getConditionsByType('warranty');
     * 
     * @example
     * // Handle missing conditions gracefully
     * $conditions = $repository->getConditionsByType('custom_type');
     * if ($conditions === null) {
     *     // Use default conditions or skip
     *     $conditions = self::DEFAULT_CONDITIONS;
     * }
     * 
     * @example
     * // Response structure from implementation
     * // Returns: "<p>Payment is due within 30 days of invoice date.</p>"
     * // Or: null (if type not found)
     * 
     * @see \App\Http\Controllers\Api\QuoteController For typical usage in quote generation
     * @see \App\Services\DocumentGenerationService For document templating that uses conditions
     */
    public function getConditionsByType(string $type): ?string;
}