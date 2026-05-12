<?php
// app/Providers/EventServiceProvider.php

namespace App\Providers;

use App\Application\Listeners\Procurement\NotifyMaterialRequestApproved;
use App\Application\Listeners\Procurement\NotifyPOCreated;
use App\Application\Listeners\Procurement\NotifyQuoteAccepted;
use App\Application\Listeners\Procurement\SendInAppNotification;
use App\Domain\Events\MaterialRequestApproved;
use App\Domain\Events\MaterialRequestCreated;
use App\Domain\Events\PurchaseOrderCreated;
use App\Domain\Events\VendorQuoteAccepted;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider  
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        //native Laravel events (optional, but recommended to keep)
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        
        MaterialRequestCreated::class => [
            SendInAppNotification::class,
            // \App\Application\Listeners\Procurement\SendEmailNotification::class, // Fase 2
            // \App\Application\Listeners\Procurement\BroadcastNotification::class, // Fase 3
        ],

        MaterialRequestApproved::class => [
            NotifyMaterialRequestApproved::class,
        ],

        VendorQuoteAccepted::class => [
            NotifyQuoteAccepted::class,
            // \App\Application\Listeners\Procurement\SendEmailNotification::class, // Fase 3
        ],

        PurchaseOrderCreated::class => [
            NotifyPOCreated::class,
            // \App\Application\Listeners\Procurement\SendEmailNotification::class, // Fase 4
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // ✅ Opcional: Si usas descubrimiento automático de eventos
        // Event::discover();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Mantener false si usas $listen explícito
    }
}
