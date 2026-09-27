<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoteResult implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $roomCode,
        public string $gameUuid,
        public ?string $eliminatedUuid,
        public int $round
    ) {}

    public function broadcastOn(): array
    {

        return [new PrivateChannel("rooms.{$this->roomCode}")];
    }

    public function broadcastAs(): string
    {
        return 'vote.result';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'room_code' => $this->roomCode,
            'game_uuid' => $this->gameUuid,
            'eliminated_uuid' => $this->eliminatedUuid,
            'round' => $this->round,
        ];
     return $payload;
    }
}