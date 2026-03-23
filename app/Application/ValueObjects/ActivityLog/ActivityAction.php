<?php

namespace App\Application\ValueObjects\ActivityLog;

/**
 * Value Object to represent the action type of an activity.
 * 
 * @immutable
 */
final class ActivityAction
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const RESTORED = 'restored';
    public const TRANSFERRED = 'transferred';

    private const VALID_ACTIONS = [
        self::CREATED,
        self::UPDATED,
        self::DELETED,
        self::RESTORED,
        self::TRANSFERRED,
    ];

    private function __construct(
        private readonly string $value
    ) {
        $this->validate($value);
    }

    /**
     * Create a Value Object from a Vtiger status
     */
    public static function fromVtigerStatus(int $status): self
    {
        return match($status) {
            0 => new self(self::CREATED),
            1 => new self(self::UPDATED),
            2 => new self(self::DELETED),
            3 => new self(self::RESTORED),
            4 => new self(self::TRANSFERRED),
            default => new self(self::UPDATED),
        };
    }

    /**
     * Create from valid string
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    private function validate(string $value): void
    {
        if (!in_array($value, self::VALID_ACTIONS, true)) {
            throw new \InvalidArgumentException(
                "Invalid activity action: {$value}"
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function verb(): string
    {
        return match($this->value) {
            self::CREATED => 'created',
            self::UPDATED => 'updated',
            self::DELETED => 'deleted',
            self::RESTORED => 'restored',
            self::TRANSFERRED => 'transferred',
            default => 'modified',
        };
    }

    public function label(): string
    {
        return match($this->value) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::RESTORED => 'Restored',
            self::TRANSFERRED => 'Transferred',
            default => 'Modified',
        };
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}