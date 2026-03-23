<?php

namespace App\Domain\Entities;

use App\Application\ValueObjects\ActivityLog\ActivityAction;
use App\Application\ValueObjects\ActivityLog\EntityType;

/**
 * Entity that represents an activity record.
 * 
 * @immutable
 */
final class ActivityLog
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly string $userName,
        private readonly ActivityAction $action,
        private readonly EntityType $entityType,
        private readonly int $entityId,
        private readonly string $entityName,
        private readonly \DateTimeImmutable $createdAt,
        private readonly string $timeAgo,
        private readonly ?string $description = null,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function userName(): string
    {
        return $this->userName;
    }

    public function action(): ActivityAction
    {
        return $this->action;
    }

    public function entityType(): EntityType
    {
        return $this->entityType;
    }

    public function entityId(): int
    {
        return $this->entityId;
    }

    public function entityName(): string
    {
        return $this->entityName;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function timeAgo(): string
    {
        return $this->timeAgo;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Generate automatic description if none exists
     */
    public function generateDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }

        return sprintf(
            '%s %s %s %s: %s',
            $this->userName,
            $this->action->verb(),
            $this->entityType->icon(),
            $this->entityType->label(),
            $this->entityName
        );
    }

    /**
     * Convert to array for API response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->userId,
                'name' => $this->userName,
                'avatar' => null,
            ],
            'action' => $this->action->value(),
            'action_label' => $this->action->label(),
            'action_verb' => $this->action->verb(),
            'entity_type' => $this->entityType->value(),
            'entity_label' => $this->entityType->label(),
            'entity_icon' => $this->entityType->icon(),
            'entity_color' => $this->entityType->color(),
            'entity_id' => $this->entityId,
            'entity_name' => $this->entityName,
            'description' => $this->generateDescription(),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ISO8601),
            'time_ago' => $this->timeAgo,
        ];
    }
}