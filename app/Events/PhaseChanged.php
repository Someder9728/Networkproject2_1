<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhaseChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $roomCode,
        private array $state
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("rooms.{$this->roomCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'phase.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code' => $this->roomCode,
            ...$this->state,
        ];
    }
}