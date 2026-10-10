<?php

namespace App\Services;

use App\Events\ChatUpdated;
use App\Models\Room;
use App\Support\RoomLock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(private RoomService $roomService) {}

    private function context(string $code, string $uuid): array
    {
        $room = $this->roomService->getRoom($code);
        abort_unless(collect($room['players'])->contains('player_uuid', $uuid), 403, 'คุณไม่ได้อยู่ในห้องนี้');
        $game = $room['game'] ?? null;
        $channels = ['all'];
        $sendable = [];
        if ($game === null) {
            if ($room['status'] === 'waiting') {
                $sendable[] = 'all';
            }
        } else {
            $me = collect($game['players'])->firstWhere('player_uuid', $uuid);
            abort_unless($me !== null && ! ($me['has_left'] ?? false), 403, 'คุณออกจากเกมแล้ว');
            $alive = $me['is_alive'];
            if (! $alive) {
                $channels[] = 'dead';
            } elseif ($me['role'] === 'werewolf') {
                $channels[] = 'werewolf';
            }
            if ($game['status'] === 'finished') {
                $sendable[] = 'all';
            } elseif ($game['status'] === 'in_progress') {
                if ($alive && in_array($game['current_phase'], ['day_discussion', 'day_voting'], true)) {
                    $sendable[] = 'all';
                }
                if (! $alive) {
                    $sendable[] = 'dead';
                } elseif ($me['role'] === 'werewolf') {
                    $sendable[] = 'werewolf';
                }
            }
        }

        return [$room, $channels, $sendable];
    }

    private function recipients(array $room, string $uuid): array
    {
        $game = $room['game'] ?? null;
        $me = $game ? collect($game['players'])->firstWhere('player_uuid', $uuid) : null;
        if ($game && ($game['status'] !== 'finished') && ($game['status'] !== 'in_progress' || ($me['is_alive'] && ! in_array($game['current_phase'], ['day_discussion', 'day_voting'], true)))) {
            return [];
        }

        return array_values(array_filter(array_map(function (array $player) use ($uuid, $game, $me) {
            if ($player['player_uuid'] === $uuid) {
                return null;
            }
            if ($game) {
                $other = collect($game['players'])->firstWhere('player_uuid', $player['player_uuid']);
                if (! $other || ($other['has_left'] ?? false) || ($game['status'] !== 'finished' && $other['is_alive'] !== $me['is_alive'])) {
                    return null;
                }
            }

            return ['uuid' => $player['player_uuid'], 'name' => $player['name']];
        }, $room['players'])));
    }

    // Private text and audio must pass the same visibility filter before leaving the server.
    private function visible(Builder $query, int $viewerId): Builder
    {
        return $query->where(function (Builder $filter) use ($viewerId) {
            $filter->whereNull('chat.players_recipient_id')->orWhere('chat.players_sender_id', $viewerId)->orWhere('chat.players_recipient_id', $viewerId);
        });
    }

    public function messages(string $code, string $uuid): array
    {
        return DB::transaction(function () use ($code, $uuid) {
            [$room, $channels, $sendable] = $this->context($code, $uuid);
            $viewerId = collect($room['players'])->firstWhere('player_uuid', $uuid)['player_id'];
            $result = [];
            foreach ($channels as $channel) {
                $query = DB::table('chat_messages as chat')->join('players as sender', 'sender.player_id', '=', 'chat.players_sender_id')
                    ->leftJoin('players as recipient', 'recipient.player_id', '=', 'chat.players_recipient_id')
                    ->where('chat.rooms_room_id', $room['room_id'])->where('chat.chat_channel', $channel);
                $messages = $this->visible($query, $viewerId)->orderByDesc('chat.chat_id')->limit(50)
                    ->get(['chat.chat_id as id', 'chat.message as content', 'chat.created_at', 'chat.audio_path', 'chat.players_recipient_id', 'sender.player_name as sender_name', 'sender.player_uuid as sender_uuid', 'recipient.player_name as recipient_name', 'recipient.player_uuid as recipient_uuid'])
                    ->reverse()->values()->map(function ($message) use ($uuid, $code) {
                        $message->audio_url = $message->audio_path ? route('rooms.chat.audio', ['code' => $code, 'message' => $message->id], false) : null;
                        $message->is_private = $message->players_recipient_id !== null;
                        unset($message->audio_path, $message->players_recipient_id);
                        $message->is_mine = $message->sender_uuid === $uuid;

                        return $message;
                    });
                $result[] = ['name' => $channel, 'can_send' => in_array($channel, $sendable, true), 'messages' => $messages];
            }

            return ['channels' => $result, 'recipients' => $this->recipients($room, $uuid)];
        });
    }

    public function send(string $code, string $uuid, string $channel, string $message, ?string $audioPath = null, ?string $audioMime = null, ?string $recipientUuid = null): void
    {
        $code = strtoupper(trim($code));
        RoomLock::make($code, 10)->block(3, function () use ($code, $uuid, $channel, $message, $audioPath, $audioMime, $recipientUuid) {
            DB::transaction(function () use ($code, $uuid, $channel, $message, $audioPath, $audioMime, $recipientUuid) {
                [$room, , $sendable] = $this->context($code, $uuid);
                $recipient = null;
                if ($recipientUuid !== null) {
                    abort_unless($channel === 'all' && collect($this->recipients($room, $uuid))->contains('uuid', $recipientUuid), 403, 'กระซิบหาผู้เล่นนี้ในช่วงนี้ไม่ได้');
                    $recipient = collect($room['players'])->firstWhere('player_uuid', $recipientUuid)['player_id'];
                } else {
                    abort_unless(in_array($channel, $sendable, true), 403, 'คุณส่งข้อความในช่องนี้ตอนนี้ไม่ได้');
                }
                $sender = Room::findOrFail($room['room_id'])->players()->where('player_uuid', $uuid)->where('has_left', false)->firstOrFail();
                DB::table('chat_messages')->insert(['chat_channel' => $channel, 'message' => $message, 'audio_path' => $audioPath, 'audio_mime' => $audioMime, 'rooms_room_id' => $room['room_id'], 'players_sender_id' => $sender->player_id, 'players_recipient_id' => $recipient, 'created_at' => now()]);
                // Shared broadcasts contain no message, names, recipient, or private typing state.
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

    public function audio(string $code, string $uuid, int $id): object
    {
        [$room, $channels] = $this->context($code, $uuid);
        $viewerId = collect($room['players'])->firstWhere('player_uuid', $uuid)['player_id'];
        $query = DB::table('chat_messages as chat')->where('chat.chat_id', $id)->where('chat.rooms_room_id', $room['room_id'])->whereIn('chat.chat_channel', $channels)->whereNotNull('chat.audio_path');
        $message = $this->visible($query, $viewerId)->first();
        abort_unless($message !== null, 404);

        return $message;
    }

    public function typing(string $code, string $uuid, ?string $channel = null, bool $active = false, ?string $recipientUuid = null): array
    {
        [$room, $channels, $sendable] = $this->context($code, $uuid);
        if ($channel !== null) {
            if ($recipientUuid !== null) {
                abort_unless($channel === 'all' && collect($this->recipients($room, $uuid))->contains('uuid', $recipientUuid), 403);
            } else {
                abort_unless(in_array($channel, $sendable, true), 403);
            }
            $key = 'typing:'.$room['room_id'].':'.$channel.':'.$uuid.':'.($recipientUuid ?? 'public');
            if ($active) {
                Cache::put($key, true, 7);
            } else {
                Cache::forget($key);
            }
        }
        $result = ['private' => []];
        foreach ($channels as $name) {
            $result[$name] = [];
            foreach ($room['players'] as $player) {
                $other = $player['player_uuid'];
                if ($other === $uuid) {
                    continue;
                }
                if (isset($room['game'])) {
                    $snapshot = collect($room['game']['players'])->firstWhere('player_uuid', $other);
                    if (! $snapshot || ($snapshot['has_left'] ?? false)) {
                        continue;
                    }
                }
                [, , $allowed] = $this->context($code, $other);
                if (in_array($name, $allowed, true) && Cache::get('typing:'.$room['room_id'].':'.$name.':'.$other.':public')) {
                    $result[$name][] = $player['name'];
                }
                if ($name === 'all' && collect($this->recipients($room, $other))->contains('uuid', $uuid) && Cache::get('typing:'.$room['room_id'].':all:'.$other.':'.$uuid)) {
                    $result['private'][] = ['uuid' => $other, 'name' => $player['name']];
                }
            }
        }

        return $result;
    }
}
