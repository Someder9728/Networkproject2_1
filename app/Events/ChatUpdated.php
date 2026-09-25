<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ChatUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $roomCode) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("rooms.{$this->roomCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.updated';
    }

    public function broadcastWith(): array
    {
        return ['room_code' => $this->roomCode];
    }
}