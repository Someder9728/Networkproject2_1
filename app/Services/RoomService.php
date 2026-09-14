<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomService
{
    public function create(string $hostName, string $hostId): array
    {
        

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

    public function join(
        string $code,
        string $playerName,
        string $playerId
        ): array {
    $code = strtoupper($code);
    $cache = Cache::store('file');

    return $cache->lock('room-lock:' . $code, 5)
        ->block(3, function () use (
            $cache,
            $code,
            $playerName,
            $playerId
        ) {
            $key = 'room:' . $code;
            $room = $cache->get($key);

            abort_if($room === null, 404, 'ไม่พบห้อง');

            // เป็นสมาชิกอยู่แล้ว: คืนห้องเดิม ไม่เพิ่มซ้ำ
            foreach ($room['players'] as $player) {
                if ($player['id'] === $playerId) {
                    return $room;
                }
            }

            // ผู้เล่นใหม่เข้าได้เฉพาะช่วง Lobby
            if ($room['status'] !== 'lobby') {
                throw ValidationException::withMessages([
                    'room' => 'ห้องนี้เริ่มเกมแล้ว',
                ]);
            }

            $room['players'][] = [
                'id' => $playerId,
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


    public function leave(string $code, string $playerId): void
    {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use ($cache, $code, $playerId) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                $isMember = collect($room['players'])
                    ->contains('id', $playerId);

                abort_unless($isMember, 403, 'คุณไม่ได้อยู่ในห้องนี้');

                if ($room['status'] !== 'lobby') {
                    throw ValidationException::withMessages([
                        'room' => 'ตอนนี้ออกได้เฉพาะช่วง Lobby',
                    ]);
                }

                // ลบสมาชิกและเรียง index ใหม่
                $room['players'] = array_values(array_filter(
                    $room['players'],
                    fn (array $player) => $player['id'] !== $playerId
                ));

                // คนสุดท้ายออก: ลบห้อง
                if (count($room['players']) === 0) {
                    $cache->forget($key);
                    return;
                }

                // Host ออก: ส่งต่อให้สมาชิกคนแรกที่เหลือ
                if ($room['host_id'] === $playerId) {
                    $room['host_id'] = $room['players'][0]['id'];
                }

                $cache->put($key, $room, now()->addHours(2));
            });
    }
}