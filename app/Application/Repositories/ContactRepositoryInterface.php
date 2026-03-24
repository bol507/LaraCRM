<?php

namespace App\Application\Repositories;


use App\Domain\Entities\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContactRepositoryInterface
{
    /**
     * Create a new contact
     * 
     * @param array $contactData Contact data
     * @param int $createdByUserId ID of the user creating the contact
     * @return int ID of the created contact
     */
    public function create(array $contactData, int $createdByUserId): int; 
    
    /**
     * Get contact by ID
     * 
     * @param int $id Contact ID
     * @return Contact|null Contact found or null if not exists
     */
    public function findById(int $id): ?Contact;
    
    /**
     * List contacts with pagination and filters
     * 
     * @param array $filters Search filters
     * @return LengthAwarePaginator<Contact>
     */
    public function findAll(array $filters = []): LengthAwarePaginator;

    /**
     * Update an existing contact
     * 
     * @param int $id Contact ID
     * @param array $contactData Data to update
     * @return bool True if updated successfully
     */
    public function update(int $id, array $contactData): bool;

    /**
     * Delete a contact (soft delete)
     * 
     * @param int $id Contact ID
     * @return bool True if deleted successfully
     */
    public function delete(int $id): bool;
    
    /**
     * Search contacts by term (autocomplete)
     * 
     * @param string $searchTerm Search term
     * @param int|null $accountId Filter by specific client
     * @param int $limit Results limit
     * @return array<Contact>
     */
    public function search(string $searchTerm, ?int $accountId = null, int $limit = 10): array;
    
    /**
     * Get contacts for a specific account
     * 
     * @param int $accountId Client ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return LengthAwarePaginator<Contact>
     */
    public function findByAccount(int $accountId, int $page = 1, int $limit = 20): LengthAwarePaginator;

    /**
     * Check if an account exists
     * 
     * @param int $accountId Client ID
     * @return bool
     */
    public function accountExists(int $accountId): bool;

    /**
     * Check if a contact exists and is not deleted
     * 
     * @param int $contactId Contact ID
     * @return bool
     */
    public function existsAndActive(int $contactId): bool;
    
    /**
     * Count contacts by client
     * 
     * @param int $clientId Client ID
     * @return int
     */
    public function countByClient(int $clientId): int;
}