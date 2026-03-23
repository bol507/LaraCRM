<?php

namespace App\Application\DTOs\ActivityLog;

use App\Domain\Entities\ActivityLog;

/**
 * DTO for activity list.
 */
final readonly class ActivityLogListDTO
{
    /**
     * @param ActivityLog[] $activities
     */
    public function __construct(
        public array $activities,
        public int $total,
    ) {}

    /**
     * Convert to array for API response
     */
    public function toArray(): array
    {
        return [
            'activities' => array_map(
                fn(ActivityLog $activity) => $activity->toArray(),
                $this->activities
            ),
            'total' => $this->total,
        ];
    }

    /**
     * Create from list of entities
     */
    public static function fromEntities(array $activities): self
    {
        return new self(
            activities: $activities,
            total: count($activities),
        );
    }
}