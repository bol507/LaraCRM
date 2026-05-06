<?php
namespace App\Domain\Entities;

use DateTime;
use InvalidArgumentException;


class CrmEntity
{
    // ==================== CONSTANTS ====================


    public const TYPE_ACCOUNTS = 'Accounts';
    public const TYPE_CONTACTS = 'Contacts';
    public const TYPE_LEADS = 'Leads';
    public const TYPE_OPPORTUNITIES = 'Potentials';
    public const TYPE_CALENDAR = 'Calendar';
    public const TYPE_HELPDESK = 'HelpDesk';
    public const TYPE_PRODUCTS = 'Products';
    public const TYPE_USERS = 'Users';
    public const TYPE_GROUPS = 'Groups';

    public const TYPE_PROJECT = 'Project';
    public const TYPE_PROJECT_TASK = 'ProjectTask';
    public const TYPE_PROJECT_MILESTONE = 'ProjectMilestone';
    public const TYPE_QUOTES = 'Quotes';

    /**
     * Presence flags
     */
    public const PRESENCE_ENABLED = 1;


    public const DELETED_YES = 1;


    public function __construct(
        private readonly int $crmid,
        private readonly int $creatorId,
        private readonly int $ownerId,
        private readonly string $entityType,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
        private readonly int $modifiedBy,
        private readonly ?string $description = null,
        private readonly ?DateTime $viewedTime = null,
        private readonly ?string $status = null,
        private readonly int $version = 0,
        private readonly int $presence = self::PRESENCE_ENABLED,
        private readonly bool $isDeleted = false,
        private readonly ?int $groupId = null,
        private readonly ?string $source = null,
        private readonly ?string $label = null,
    ) {
        $this->validate();
    }

    // ==================== VALIDATION ====================

    /**
     * Validate entity invariants
     *
     * @throws InvalidArgumentException If business rules are violated
     */
    private function validate(): void
    {
        if ($this->crmid <= 0) {
            throw new InvalidArgumentException('CRM ID must be positive');
        }
        if ($this->creatorId <= 0) {
            throw new InvalidArgumentException('Creator ID must be positive');
        }
        if ($this->ownerId <= 0) {
            throw new InvalidArgumentException('Owner ID must be positive');
        }
        if (!in_array($this->entityType, $this->getValidEntityTypes(), true)) {
            throw new InvalidArgumentException("Invalid entity type: $this->entityType");
        }
        if ($this->presence < 0 || $this->presence > 1) {
            throw new InvalidArgumentException('Presence must be 0 or 1');
        }
    }

    /**
     * Get list of valid entity types (can be extended per project needs)
     *
     * @return string[]
     */
    protected function getValidEntityTypes(): array
    {
        return [
            self::TYPE_ACCOUNTS,
            self::TYPE_CONTACTS,
            self::TYPE_LEADS,
            self::TYPE_OPPORTUNITIES,
            self::TYPE_CALENDAR,
            self::TYPE_HELPDESK,
            self::TYPE_PRODUCTS,
            self::TYPE_USERS,
            self::TYPE_GROUPS,
            self::TYPE_PROJECT,
            self::TYPE_PROJECT_TASK,
            self::TYPE_PROJECT_MILESTONE,
            self::TYPE_QUOTES,
        ];
    }

    // ==================== GETTERS ====================

    public function getId(): int { return $this->crmid; }
    public function getCreatorId(): int { return $this->creatorId; }
    public function getOwnerId(): int { return $this->ownerId; }
    public function getEntityType(): string { return $this->entityType; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getUpdatedAt(): DateTime { return $this->updatedAt; }
    public function getModifiedBy(): int { return $this->modifiedBy; }
    public function getDescription(): ?string { return $this->description; }
    public function getViewedTime(): ?DateTime { return $this->viewedTime; }
    public function getStatus(): ?string { return $this->status; }
    public function getVersion(): int { return $this->version; }
    public function getPresence(): int { return $this->presence; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function getGroupId(): ?int { return $this->groupId; }
    public function getSource(): ?string { return $this->source; }
    public function getLabel(): ?string { return $this->label; }

    // ==================== BUSINESS LOGIC ====================

    /**
     * Check if record is active (not deleted and presence enabled)
     */
    public function isActive(): bool
    {
        return !$this->isDeleted && $this->presence === self::PRESENCE_ENABLED;
    }

    // ==================== SERIALIZATION (for debugging/DTO mapping) ====================

    /**
     * Convert entity to array for internal use (NOT for API/BD)
     *
     * This is ONLY for debugging or passing to Mappers/DTOs.
     * The array structure is internal and should not be relied upon by external code.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crmid' => $this->crmid,
            'smcreatorid' => $this->creatorId,
            'smownerid' => $this->ownerId,
            'setype' => $this->entityType,
            'createdtime' => $this->createdAt->format('Y-m-d H:i:s'),
            'modifiedtime' => $this->updatedAt->format('Y-m-d H:i:s'),
            'modifiedby' => $this->modifiedBy,
            'description' => $this->description,
            'viewedtime' => $this->viewedTime?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'version' => $this->version,
            'presence' => $this->presence,
            'deleted' => $this->isDeleted ? 1 : 0,
            'smgroupid' => $this->groupId,
            'source' => $this->source,
            'label' => $this->label,
        ];
    }
}
