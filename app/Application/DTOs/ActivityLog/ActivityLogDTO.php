<?php

namespace App\Application\DTOs\ActivityLog;

use App\Application\ValueObjects\ActivityLog\ActivityAction;
use App\Application\ValueObjects\ActivityLog\EntityType;
use App\Domain\Entities\ActivityLog;

/**
 * DTO for creating an ActivityLog entity from raw data.
 */
final readonly class ActivityLogDTO
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $userName,
        public int $vtigerStatus,
        public string $vtigerModule,
        public int $entityId,
        public string $entityName,
        public \DateTimeImmutable $createdAt,
        public ?string $description = null,
    ) {}

    /**
     * Convert DTO to Entity
     */
    public function toEntity(): ActivityLog
    {
        return new ActivityLog(
            id: $this->id,
            userId: $this->userId,
            userName: $this->userName,
            action: ActivityAction::fromVtigerStatus($this->vtigerStatus),
            entityType: EntityType::fromVtigerModule($this->vtigerModule),
            entityId: $this->entityId,
            entityName: $this->entityName,
            createdAt: $this->createdAt,
            timeAgo: $this->calculateTimeAgo($this->createdAt),
            description: $this->description,
        );
    }

    /**
     * Calculate relative time (X minutes/hours/days ago)
     */
    private function calculateTimeAgo(\DateTimeImmutable $date): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($date);

        if ($diff->days > 0) {
            return "{$diff->days} day" . ($diff->days > 1 ? 's ago' : ' ago');
        }
        if ($diff->h > 0) {
            return "{$diff->h} hour" . ($diff->h > 1 ? 's ago' : ' ago');
        }
        if ($diff->i > 0) {
            return "{$diff->i} minute" . ($diff->i > 1 ? 's ago' : ' ago');
        }
        return 'just now';
    }

    /**
     * Create DTO from database array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            userId: (int) $data['whodid'],
            userName: trim("{$data['first_name']} {$data['last_name']}") ?: $data['user_name'],
            vtigerStatus: (int) $data['status'],
            vtigerModule: $data['module'],
            entityId: (int) $data['crmid'],
            entityName: $data['entity_name'] ?? 'Unnamed',
            createdAt: new \DateTimeImmutable($data['changedon']),
            description: $data['description'] ?? null,
        );
    }
}