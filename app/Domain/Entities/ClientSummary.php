<?php

namespace App\Domain\Entities;

/**
 * Entity representing a summary of related entities for a client.
 * 
 * This is a read-only data transfer object used to return aggregated
 * counts of opportunities, quotes, projects, and contacts associated
 * with a client account.
 */
class ClientSummary
{
    public function __construct(
        public readonly int $clientId,
        public readonly int $opportunitiesCount = 0,
        public readonly int $quotesCount = 0,
        public readonly int $projectsCount = 0,
        public readonly int $contactsCount = 0,
        public readonly ?string $lastActivity = null
    ) {}

    /**
     * Convert the entity to an array for API response.
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'clientId' => $this->clientId,
            'opportunitiesCount' => $this->opportunitiesCount,
            'quotesCount' => $this->quotesCount,
            'projectsCount' => $this->projectsCount,
            'contactsCount' => $this->contactsCount,
            'lastActivity' => $this->lastActivity,
        ];
    }

    /**
     * Create a ClientSummary instance from an array.
     * 
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientId: (int) ($data['clientId'] ?? 0),
            opportunitiesCount: (int) ($data['opportunitiesCount'] ?? 0),
            quotesCount: (int) ($data['quotesCount'] ?? 0),
            projectsCount: (int) ($data['projectsCount'] ?? 0),
            contactsCount: (int) ($data['contactsCount'] ?? 0),
            lastActivity: $data['lastActivity'] ?? null,
        );
    }
}