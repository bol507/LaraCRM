<?php

namespace App\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaterialRequestApprovedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $requestId,
        public readonly int $projectId,
        public readonly int $approverId,
        public readonly string $status, // 'approved', 'rejected', 'partially_approved'
        public readonly ?string $notes = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->projectId}"),
        ];
    }

    public function broadcastWith(): array
    {
        $action = match($this->status) {
            'approved' => 'aprobada',
            'rejected' => 'rechazada',
            default => 'aprobada parcialmente',
        };

        return [
            'type' => 'material_request.approved',
            'request_id' => $this->requestId,
            'status' => $this->status,
            'message' => "La solicitud de materiales #{$this->requestId} ha sido {$action}.",
            'notes' => $this->notes,
            'timestamp' => now()->toISOString(),
        ];
    }
}