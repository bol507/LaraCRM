<?php

namespace App\Application\ValueObjects\ActivityLog;

/**
 * Value Object to represent the entity type.
 * 
 * @immutable
 */
final class EntityType
{
    public const CLIENT = 'client';
    public const PROJECT = 'project';
    public const QUOTE = 'quote';
    public const ACTIVITY = 'activity';
    public const OPPORTUNITY = 'opportunity';
    public const CONTACT = 'contact';

    private const VALID_TYPES = [
        self::CLIENT,
        self::PROJECT,
        self::QUOTE,
        self::ACTIVITY,
        self::OPPORTUNITY,
        self::CONTACT,
    ];

    private const MODULE_MAP = [
        'Account' => self::CLIENT,
        'Project' => self::PROJECT,
        'Quotes' => self::QUOTE,
        'Invoice' => self::QUOTE,
        'Potentials' => self::OPPORTUNITY,
        'Contacts' => self::CONTACT,
        'Calendar' => self::ACTIVITY,
        'Tasks' => self::ACTIVITY,
        'Events' => self::ACTIVITY,
    ];

    private const CONFIG = [
        self::CLIENT => ['label' => 'Client', 'icon' => '🏢', 'color' => 'text-blue-500'],
        self::PROJECT => ['label' => 'Project', 'icon' => '📋', 'color' => 'text-purple-500'],
        self::QUOTE => ['label' => 'Quote', 'icon' => '📄', 'color' => 'text-yellow-500'],
        self::ACTIVITY => ['label' => 'Activity', 'icon' => '✓', 'color' => 'text-green-500'],
        self::OPPORTUNITY => ['label' => 'Opportunity', 'icon' => '💰', 'color' => 'text-orange-500'],
        self::CONTACT => ['label' => 'Contact', 'icon' => '👤', 'color' => 'text-pink-500'],
    ];

    private function __construct(
        private readonly string $value
    ) {
        $this->validate($value);
    }

    /**
     * Create from a Vtiger module
     */
    public static function fromVtigerModule(string $module): self
    {
        $type = self::MODULE_MAP[$module] ?? strtolower($module);
        return new self($type);
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
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid entity type: {$value}"
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return self::CONFIG[$this->value]['label'];
    }

    public function icon(): string
    {
        return self::CONFIG[$this->value]['icon'];
    }

    public function color(): string
    {
        return self::CONFIG[$this->value]['color'];
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}