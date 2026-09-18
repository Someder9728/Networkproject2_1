<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomService
{
    public function create(
            string $hostName,
            string $hostUuid,
            string $difficulty
        ): array {
            $room = [
                'code' => strtoupper(Str::random(6)),
                'status' => 'waiting',
                'difficulty' => $difficulty,
                'host_uuid' => $hostUuid,
                'players' => [
                    [
                        'player_uuid' => $hostUuid,
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
        string $playerUuid
        ): array {
    $code = strtoupper($code);
    $cache = Cache::store('file');

    return $cache->lock('room-lock:' . $code, 5)
        ->block(3, function () use (
            $cache,
            $code,
            $playerName,
            $playerUuid
        ) {
            $key = 'room:' . $code;
            $room = $cache->get($key);

            abort_if($room === null, 404, 'ไม่พบห้อง');

            // เป็นสมาชิกอยู่แล้ว: คืนห้องเดิม ไม่เพิ่มซ้ำ
            foreach ($room['players'] as $player) {
                if ($player['player_uuid'] === $playerUuid) {
                    return $room;
                }
            }

            // ผู้เล่นใหม่เข้าได้เฉพาะช่วง Lobby
            if ($room['status'] !== 'waiting') {
                throw ValidationException::withMessages([
                    'room' => 'ห้องนี้เริ่มเกมแล้ว',
                ]);
            }

            $room['players'][] = [
                'player_uuid' => $playerUuid,
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


    public function leave(string $code, string $playerUuid): void
    {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use ($cache, $code, $playerUuid) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                $isMember = collect($room['players'])
                    ->contains('player_uuid', $playerUuid);

                abort_unless($isMember, 403, 'คุณไม่ได้อยู่ในห้องนี้');

                if ($room['status'] !== 'waiting') {
                    throw ValidationException::withMessages([
                        'room' => 'ตอนนี้ออกได้เฉพาะช่วง Lobby',
                    ]);
                }

                // ลบสมาชิกและเรียง index ใหม่
                $room['players'] = array_values(array_filter(
                    $room['players'],
                    fn (array $player) => $player['player_uuid'] !== $playerUuid
                ));

                // คนสุดท้ายออก: ลบห้อง
                if (count($room['players']) === 0) {
                    $cache->forget($key);
                    return;
                }

                // Host ออก: ส่งต่อให้สมาชิกคนแรกที่เหลือ
                if ($room['host_uuid'] === $playerUuid) {
                    $room['host_uuid'] = $room['players'][0]['player_uuid'];
                }

                $cache->put($key, $room, now()->addHours(2));
            });
    }
}