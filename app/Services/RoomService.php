<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomService
{
    public function create(string $hostName): array
    {
        $hostId = (string) Str::uuid();

        $room = [
            'code' => strtoupper(Str::random(6)),
            'status' => 'lobby',
            'host_id' => $hostId,
            'players' => [
                [
                    'id' => $hostId,
                    'name' => $hostName,
                ],
            ],
        ];

        Cache::store('file')->put(
            'room:' . $room['code'],
            $room,
            now()->addHours(2)
        );

        return $room;
    }

    public function join(string $code, string $playerName): array
    {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        return $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use ($cache, $code, $playerName) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'No room found');

                if ($room['status'] !== 'lobby') {
                    throw ValidationException::withMessages([
                        'room' => 'Room is now in game',
                    ]);
                }

                $room['players'][] = [
                    'id' => (string) Str::uuid(),
                    'name' => $playerName,
                ];

                $cache->put($key, $room, now()->addHours(2));

                return $room;
            });
            
    }

    public function getRoom(string $code): array
    {
        $room = Cache::store('file')->get(
            'room:' . strtoupper($code)
        );

        abort_if($room === null, 404, 'Room not found');

        return $room;
    }
}