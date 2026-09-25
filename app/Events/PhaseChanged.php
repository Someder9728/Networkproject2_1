<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhaseChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public string $phase;
    public string $roomId;

    public function __construct(string $roomId, string $phase)
    {
        $this->roomId = $roomId;
        $this->phase = $phase;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("rooms.{$this->roomId}")
        ];
    }

    public function broadcastAs(): string
    {
        return 'phase.changed';
    }
}
