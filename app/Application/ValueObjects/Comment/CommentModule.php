<?php

namespace App\Application\ValueObjects\Comment;

use InvalidArgumentException;

/**
 * Comment Module Value Object
 *
 * Centralizes the list of Vtiger modules that support comments and
 * provides the mapping between the public module name (used by the API
 * and the frontend) and the Vtiger setype stored in vtiger_crmentity.
 *
 * Supported modules:
 * - Project (proyectos)
 * - Quotes (cotizaciones)
 * - Calendar (tareas/actividades)
 * - Accounts (clientes)
 * - Contacts (contactos)
 * - Potentials (oportunidades)
 * - HelpDesk (tickets)
 *
 * @package App\Application\ValueObjects\Comment
 */
final class CommentModule
{
    public const PROJECT = 'Project';

    public const QUOTES = 'Quotes';

    public const CALENDAR = 'Calendar';

    public const ACCOUNTS = 'Accounts';

    public const CONTACTS = 'Contacts';

    public const POTENTIALS = 'Potentials';

    public const HELPDESK = 'HelpDesk';

    /**
     * List of all valid module names.
     *
     * @var list<string>
     */
    private const ALLOWED_MODULES = [
        self::PROJECT,
        self::QUOTES,
        self::CALENDAR,
        self::ACCOUNTS,
        self::CONTACTS,
        self::POTENTIALS,
        self::HELPDESK,
    ];

    /**
     * Vtiger setype used in vtiger_crmentity for each module.
     *
     * @var array<string, string>
     */
    private const SETYPE_MAP = [
        self::PROJECT => 'Project',
        self::QUOTES => 'Quotes',
        self::CALENDAR => 'Calendar',
        self::ACCOUNTS => 'Accounts',
        self::CONTACTS => 'Contacts',
        self::POTENTIALS => 'Potentials',
        self::HELPDESK => 'HelpDesk',
    ];

    /**
     * Check if a module name supports comments.
     *
     * @param string $module Public module name (e.g., 'Potentials')
     * @return bool True if the module is supported, false otherwise
     */
    public static function isSupported(string $module): bool
    {
        return in_array($module, self::ALLOWED_MODULES, true);
    }

    /**
     * Resolve the Vtiger setype for a module name.
     *
     * @param string $module Public module name (e.g., 'Potentials')
     * @return string The Vtiger setype stored in vtiger_crmentity
     *
     * @throws InvalidArgumentException If the module is not supported
     */
    public static function toSetype(string $module): string
    {
        return self::SETYPE_MAP[$module] ?? throw new InvalidArgumentException(
            "Module '{$module}' not allowed for comments. " .
            'Valid modules: ' . implode(', ', self::ALLOWED_MODULES)
        );
    }

    /**
     * Get the list of all supported module names.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return self::ALLOWED_MODULES;
    }
}