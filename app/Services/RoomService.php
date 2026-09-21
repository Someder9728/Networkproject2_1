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
        $cache = Cache::store('file');

        return $cache->lock('room-create-lock', 5)
            ->block(3, function () use (
                $cache,
                $hostName,
                $hostUuid,
                $difficulty
            ) {
                // ลองสร้างรหัสใหม่ได้สูงสุด 10 ครั้ง
                for ($attempt = 0; $attempt < 10; $attempt++) {
                    $code = strtoupper(Str::random(6));
                    $key = 'room:' . $code;

                    if ($cache->has($key)) {
                        continue;
                    }

                    $room = [
                        'code' => $code,
                        'status' => 'waiting',
                        'game_uuid' => null,
                        'difficulty' => $difficulty,
                        'host_uuid' => $hostUuid,
                        'players' => [
                            [
                                'player_uuid' => $hostUuid,
                                'name' => $hostName,
                            ],
                        ],
                    ];

                    $cache->put($key, $room, now()->addHours(2));

                    return $room;
                }

                throw ValidationException::withMessages([
                    'room' => 'สร้างรหัสห้องไม่สำเร็จ กรุณาลองใหม่',
                ]);
            });
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

            if ($room === null) {
                throw ValidationException::withMessages([
                    'code' => 'ไม่พบห้องนี้ กรุณาตรวจสอบรหัสห้อง',
                ]);
            }

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
            
            if (count($room['players']) >= 6) {
                throw ValidationException::withMessages([
                    'room' => 'ห้องเต็มแล้ว รับผู้เล่นได้สูงสุด 6 คน',
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

    public function start(
        string $code,
        string $playerUuid,
        GameService $gameService
    ): array {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        return $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use (
                $cache,
                $code,
                $playerUuid,
                $gameService
            ) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                abort_unless(
                    $room['host_uuid'] === $playerUuid,
                    403,
                    'เฉพาะ Host เท่านั้นที่เริ่มเกมได้'
                );

                if (
                    $room['status'] !== 'waiting'
                    || ($room['game_uuid'] ?? null) !== null
                ) {
                    throw ValidationException::withMessages([
                        'room' => 'ห้องนี้เริ่มเกมไปแล้ว',
                    ]);
                }

                if (!in_array(count($room['players']), [4, 6], true)) {
                    throw ValidationException::withMessages([
                        'room' => 'ต้องมีผู้เล่น 4 หรือ 6 คนจึงเริ่มเกมได้',
                    ]);
                }

                $game = $gameService->buildSession($room);

                $room['game_uuid'] = $game['game_uuid'];
                $room['status'] = 'initializing';
                $room['game'] = $game;

                $cache->put($key, $room, now()->addHours(2));

                return redirect()->route('games.show', [
                    'code' => $room['code'],
                ]);
            });
    }

    public function getGameView(
        string $code,
        string $playerUuid
    ): array {
        $room = $this->getRoom($code);

        $isMember = collect($room['players'])
            ->contains('player_uuid', $playerUuid);

        abort_unless($isMember, 403, 'คุณไม่ได้อยู่ในห้องนี้');

        $game = $room['game'] ?? null;

        abort_if($game === null, 404, 'ห้องนี้ยังไม่ได้เริ่มเกม');

        return [
            'game_uuid' => $game['game_uuid'],
            'room_code' => $game['room_code'],
            'difficulty' => $game['difficulty'],
            'status' => $game['status'],
            'current_phase' => $game['current_phase'],
            'current_round' => $game['current_round'],
            'players' => array_map(
                fn (array $player) => [
                    'name' => $player['name'],
                    'is_alive' => $player['is_alive'],
                ],
                $game['players']
            ),
        ];
    }


    public function findRoomForPlayer(
        string $code,
        string $playerUuid
    ): ?array {
        $room = Cache::store('file')->get(
            'room:' . strtoupper($code)
        );

        if ($room === null) {
            return null;
        }

        $isMember = collect($room['players'])
            ->contains('player_uuid', $playerUuid);

        if (!$isMember) {
            return null;
        }

        return [
            'code' => $room['code'],
            'game_uuid' => $room['game_uuid'] ?? null,
        ];
    }

}