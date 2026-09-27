<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NightEventTriggered implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $roomCode,
        public string $gameUuid,
        public array $event,
        public int $round
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("rooms.{$this->roomCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'night.event.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code' => $this->roomCode,
            'game_uuid' => $this->gameUuid,
            'event_id' => $this->event['id'],
            'round' => $this->round,
        ];
    }
}