<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $roomId,
        public string $senderId,
        public string $senderName,
        public string $content,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("rooms.{$this->roomId}")
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'channel' => 'general',
            'message_id' => uniqid(),
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'content' => $this->content,
            'metadata' => [
                'game_id' => null,
                'round' => null,
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}