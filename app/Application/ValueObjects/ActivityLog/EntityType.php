<?php

namespace App\Application\ValueObjects\ActivityLog;

use Illuminate\Support\Facades\Log;

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
    public const TASK = 'task';
    public const DOCUMENT = 'document';
    public const EMAIL = 'email';
    public const TICKET = 'ticket';
    public const COMMENT = 'comment';
    public const PRODUCT = 'product';
    public const MILESTONE = 'milestone';
    public const PURCHASE_ORDER = 'purchase_order';
    public const SALES_ORDER = 'sales_order';
    public const CONTRACT = 'contract';
    public const SERVICE = 'service';
    public const VENDOR = 'vendor';

    private const VALID_TYPES = [
        self::CLIENT,
        self::PROJECT,
        self::QUOTE,
        self::ACTIVITY,
        self::OPPORTUNITY,
        self::CONTACT,
        self::TASK,
        self::DOCUMENT,
        self::EMAIL,
        self::TICKET,
        self::COMMENT,
        self::PRODUCT,
        self::MILESTONE,
        self::PURCHASE_ORDER,
        self::SALES_ORDER,
        self::CONTRACT,
        self::SERVICE,
        self::VENDOR,
    ];

    private const MODULE_MAP = [

        'Accounts' => self::CLIENT,
        'Contacts' => self::CONTACT,
        'Potentials' => self::OPPORTUNITY,
        'Quotes' => self::QUOTE,
        'Invoice' => self::QUOTE, 
        'PurchaseOrder' => self::PURCHASE_ORDER,
        'SalesOrder' => self::SALES_ORDER,
        'Vendors' => self::VENDOR,
        
        
        'Project' => self::PROJECT,
        'ProjectTask' => self::TASK,
        'ProjectMilestone' => self::MILESTONE,
        
        
        'Calendar' => self::ACTIVITY,
        'Events' => self::ACTIVITY,
        'Tasks' => self::ACTIVITY,
        'Emails' => self::EMAIL,
        'Documents' => self::DOCUMENT,
        'HelpDesk' => self::TICKET,
        'ServiceContracts' => self::CONTRACT,
        
       
        'Products' => self::PRODUCT,
        'Services' => self::SERVICE,
        'ModComments' => self::COMMENT,
    ];

    private const CONFIG = [
        self::CLIENT => ['label' => 'Cliente', 'icon' => '🏢', 'color' => 'text-blue-500'],
        self::PROJECT => ['label' => 'Proyecto', 'icon' => '📋', 'color' => 'text-purple-500'],
        self::QUOTE => ['label' => 'Cotización', 'icon' => '📄', 'color' => 'text-yellow-500'],
        self::ACTIVITY => ['label' => 'Actividad', 'icon' => '✓', 'color' => 'text-green-500'],
        self::OPPORTUNITY => ['label' => 'Oportunidad', 'icon' => '💰', 'color' => 'text-orange-500'],
        self::CONTACT => ['label' => 'Contacto', 'icon' => '👤', 'color' => 'text-pink-500'],
        self::TASK => ['label' => 'Tarea', 'icon' => '✓', 'color' => 'text-green-500'],
        self::DOCUMENT => ['label' => 'Documento', 'icon' => '📎', 'color' => 'text-gray-500'],
        self::EMAIL => ['label' => 'Email', 'icon' => '✉️', 'color' => 'text-blue-500'],
        self::TICKET => ['label' => 'Ticket', 'icon' => '🎫', 'color' => 'text-orange-500'],
        self::COMMENT => ['label' => 'Comentario', 'icon' => '💬', 'color' => 'text-gray-500'],
        self::PRODUCT => ['label' => 'Producto', 'icon' => '📦', 'color' => 'text-purple-500'],
        self::MILESTONE => ['label' => 'Hito', 'icon' => '🚩', 'color' => 'text-yellow-500'],
        self::PURCHASE_ORDER => ['label' => 'Orden de Compra', 'icon' => '🛒', 'color' => 'text-blue-500'],
        self::SALES_ORDER => ['label' => 'Orden de Venta', 'icon' => '💵', 'color' => 'text-green-500'],
        self::CONTRACT => ['label' => 'Contrato', 'icon' => '📝', 'color' => 'text-blue-500'],
        self::SERVICE => ['label' => 'Servicio', 'icon' => '⚙️', 'color' => 'text-gray-500'],
        self::VENDOR => ['label' => 'Proveedor', 'icon' => '🏭', 'color' => 'text-gray-500'],
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
        
        if (isset(self::MODULE_MAP[$module])) {
            return new self(self::MODULE_MAP[$module]);
        }
        
        return new self(strtolower($module));
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
        if (empty($value)) {
            throw new \InvalidArgumentException('Entity type cannot be empty');
        }
        
       
        if (!in_array($value, self::VALID_TYPES, true)) {
            Log::debug("Unknown entity type: {$value}");
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return self::CONFIG[$this->value]['label'] 
            ?? ucfirst(str_replace('_', ' ', $this->value));
    }

    public function icon(): string
    {
        return self::CONFIG[$this->value]['icon'] ?? '📄';
    }

    public function color(): string
    {
        return self::CONFIG[$this->value]['color'] ?? 'text-gray-500';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
