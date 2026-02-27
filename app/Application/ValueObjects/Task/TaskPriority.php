<?php

namespace App\Application\ValueObjects;

/**
 * Task Priority Value Object
 */
class TaskPriority
{
    public const LOW = 'Low';
    public const MEDIUM = 'Medium';
    public const HIGH = 'High';

    private const VALID_PRIORITIES = [
        self::LOW,
        self::MEDIUM,
        self::HIGH,
    ];

    public function __construct(
        private readonly string $priority
    ) {
        $normalized = ucfirst(strtolower($priority));
        
        if (!$this->isValid($normalized)) {
            throw new \InvalidArgumentException("Invalid task priority: {$priority}");
        }

        $this->priority = $normalized;
    }

    public function value(): string
    {
        return $this->priority;
    }

    public function isHigh(): bool
    {
        return $this->priority === self::HIGH;
    }

    public function isLow(): bool
    {
        return $this->priority === self::LOW;
    }

    public static function isValid(string $priority): bool
    {
        $normalized = ucfirst(strtolower($priority));
        return in_array($normalized, self::VALID_PRIORITIES, true);
    }

    public static function all(): array
    {
        return self::VALID_PRIORITIES;
    }
}