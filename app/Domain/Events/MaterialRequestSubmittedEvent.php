<?php

namespace App\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaterialRequestSubmittedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $requestId,
        public readonly int $projectId,
        public readonly int $requestedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->projectId}"),
            new PrivateChannel("user.{$this->requestedBy}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'material_request.submitted',
            'request_id' => $this->requestId,
            'project_id' => $this->projectId,
            'message' => "Nueva solicitud de materiales #{$this->requestId} creada.",
            'timestamp' => now()->toISOString(),
        ];
    }
}