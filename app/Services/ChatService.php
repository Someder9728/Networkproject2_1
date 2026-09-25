<?php

namespace App\Services;

use App\Events\ChatUpdated;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(
        private RoomService $roomService
    ) {}

    private function context(string $code, string $uuid): array
    {
        $room = $this->roomService->getRoom($code);

        abort_unless(
            collect($room['players'])->contains('player_uuid', $uuid),
            403,
            'คุณไม่ได้อยู่ในห้องนี้'
        );

        $game = $room['game'] ?? null;
        $channels = ['all'];
        $sendable = [];

        if ($game === null) {
            if ($room['status'] === 'waiting') {
                $sendable[] = 'all';
            }
        } else {
            $me = collect($game['players'])
                ->firstWhere('player_uuid', $uuid);

            abort_unless(
                $me !== null && !($me['has_left'] ?? false),
                403,
                'คุณออกจากเกมแล้ว'
            );

            $alive = $me['is_alive'];

            if (!$alive) {
                $channels[] = 'dead';
            } elseif ($me['role'] === 'werewolf') {
                $channels[] = 'werewolf';
            }

            if ($game['status'] === 'finished') {
                $sendable[] = 'all';
            } elseif ($game['status'] === 'in_progress') {
                if (
                    $alive
                    && in_array(
                        $game['current_phase'],
                        ['day_discussion', 'day_voting'],
                        true
                    )
                ) {
                    $sendable[] = 'all';
                }

                if (!$alive) {
                    $sendable[] = 'dead';
                } elseif ($me['role'] === 'werewolf') {
                    $sendable[] = 'werewolf';
                }
            }
        }

        return [$room, $channels, $sendable];
    }

    public function messages(string $code, string $uuid): array
    {
        return DB::transaction(function () use ($code, $uuid) {
            [$room, $channels, $sendable] = $this->context($code, $uuid);

            $result = [];

            foreach ($channels as $channel) {
                $messages = DB::table('chat_messages as chat')
                    ->join(
                        'players as sender',
                        'sender.player_id',
                        '=',
                        'chat.players_sender_id'
                    )
                    ->where('chat.rooms_room_id', $room['room_id'])
                    ->where('chat.chat_channel', $channel)
                    ->orderByDesc('chat.chat_id')
                    ->limit(50)
                    ->get([
                        'chat.chat_id as id',
                        'chat.message as content',
                        'chat.created_at',
                        'sender.player_name as sender_name',
                    ])
                    ->reverse()
                    ->values();

                $result[] = [
                    'name' => $channel,
                    'can_send' => in_array($channel, $sendable, true),
                    'messages' => $messages,
                ];
            }

            return ['channels' => $result];
        });
    }

    public function send(
        string $code,
        string $uuid,
        string $channel,
        string $message
    ): void {
        $code = strtoupper(trim($code));

        Cache::store('file')->lock('room-lock:' . $code, 10)
            ->block(3, function () use (
                $code,
                $uuid,
                $channel,
                $message
            ) {
                DB::transaction(function () use (
                    $code,
                    $uuid,
                    $channel,
                    $message
                ) {
                    [$room, $channels, $sendable] =
                        $this->context($code, $uuid);

                    abort_unless(
                        in_array($channel, $sendable, true),
                        403,
                        'คุณส่งข้อความในช่องนี้ตอนนี้ไม่ได้'
                    );

                    $sender = Room::findOrFail($room['room_id'])
                        ->players()
                        ->where('player_uuid', $uuid)
                        ->where('has_left', false)
                        ->firstOrFail();

                    DB::table('chat_messages')->insert([
                        'chat_channel' => $channel,
                        'message' => $message,
                        'rooms_room_id' => $room['room_id'],
                        'players_sender_id' => $sender->player_id,
                        'created_at' => now(),
                    ]);

                    DB::afterCommit(function () use ($code) {
                        try {
                            ChatUpdated::dispatch($code);
                        } catch (\Throwable $exception) {
                            report($exception);
                        }
                    });
                });
            });
    }
}