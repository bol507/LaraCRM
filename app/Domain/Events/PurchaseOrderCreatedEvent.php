<?php

namespace App\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $poId,
        public readonly string $poNumber,
        public readonly int $projectId,
        public readonly ?int $vendorId = null,
        public readonly float $totalAmount = 0.0,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->projectId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'purchase_order.created',
            'po_id' => $this->poId,
            'po_number' => $this->poNumber,
            'project_id' => $this->projectId,
            'total_amount' => $this->totalAmount,
            'message' => "Nueva orden de compra {$this->poNumber} generada para el proyecto.",
            'timestamp' => now()->toISOString(),
        ];
    }
}