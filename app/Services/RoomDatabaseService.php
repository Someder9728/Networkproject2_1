<?php

namespace App\Services;

use App\GameLogic\DifficultyConfig;
use App\Models\Player;
use App\Models\Room;
use App\Support\AvatarCatalog;
use App\Support\RoomLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomDatabaseService
{
    public function create(
        string $hostName,
        string $hostUuid,
        string $difficulty,
        int $playerLimit = 4,
        string $gameMode = 'normal'
    ): Room {
        abort_unless(in_array($playerLimit, DifficultyConfig::supportedPlayerCounts(), true) && in_array($gameMode, ['normal', 'short'], true), 422, 'การตั้งค่าห้องไม่ถูกต้อง');

        return DB::transaction(function () use (
            $hostName,
            $hostUuid,
            $difficulty,
            $playerLimit,
            $gameMode
        ) {
            if (
                Player::where('player_uuid', $hostUuid)
                    ->where('has_left', false)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'room' => 'กรุณาออกจากห้องเดิมก่อนสร้างห้องใหม่',
                ]);
            }

            do {
                $code = strtoupper(Str::random(6));
            } while (Room::where('room_code', $code)->exists());

            $room = Room::create([
                'room_code' => $code,
                'room_status' => 'waiting',
                'difficulty' => $difficulty,
                'player_limit' => $playerLimit,
                'game_mode' => $gameMode,
            ]);

            $room->players()->create([
                'player_name' => $hostName,
                'player_uuid' => $hostUuid,
                'is_host' => true,
                'is_alive' => true,
                'is_connected' => true,
                'has_left' => false,
            ]);

            return $room->load('players');
        });
    }

    public function findLobby(string $code): ?array
    {
        $room = Room::with([
            'players' => fn ($query) => $query
                ->where('has_left', false)
                ->orderBy('player_id'),
        ])
            ->where('room_code', strtoupper($code))
            ->first();

        if ($room === null) {
            return null;
        }

        $host = $room->players->firstWhere('is_host', true);

        return [
            'room_id' => $room->room_id,
            'code' => $room->room_code,
            'status' => $room->room_status,
            'difficulty' => $room->difficulty,
            'host_uuid' => $host?->player_uuid,
            'player_limit' => $room->player_limit,
            'game_mode' => $room->game_mode,
            'start_countdown_at' => $room->start_countdown_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'players' => $room->players->map(
                fn (Player $player) => [
                    'player_id' => $player->player_id,
                    'player_uuid' => $player->player_uuid,
                    'name' => $player->player_name,
                    'avatar' => AvatarCatalog::normalize($player->avatar),
                    'is_ready' => $player->is_ready,
                ]
            )->all(),
            'game_uuid' => $room->game_uuid,
        ];
    }

    public function join(
        string $code,
        string $playerName,
        string $playerUuid
    ): Room {
        $code = strtoupper(trim($code));

        return RoomLock::make($code, 10)
            ->block(3, function () use ($code, $playerName, $playerUuid) {
                return DB::transaction(function () use (
                    $code,
                    $playerName,
                    $playerUuid
                ) {
                    $room = Room::where('room_code', $code)->first();

                    if ($room === null) {
                        throw ValidationException::withMessages([
                            'code' => 'ไม่พบห้องนี้ กรุณาตรวจสอบรหัสห้อง',
                        ]);
                    }

                    // ตรวจประวัติของผู้เล่นในห้องที่กำลังเข้า
                    $membership = $room->players()
                        ->where('player_uuid', $playerUuid)
                        ->first();

                    if ($membership !== null && $membership->has_left) {
                        throw ValidationException::withMessages([
                            'room' => 'คุณออกจากห้องนี้ถาวรแล้ว ไม่สามารถกลับเข้าได้',
                        ]);
                    }

                    // ตรวจว่ากำลังอยู่ห้องอื่นหรือไม่
                    $inAnotherRoom = Player::where('player_uuid', $playerUuid)
                        ->where('has_left', false)
                        ->where('rooms_room_id', '!=', $room->room_id)
                        ->exists();

                    if ($inAnotherRoom) {
                        throw ValidationException::withMessages([
                            'room' => 'กรุณาออกจากห้องเดิมก่อนเข้าห้องใหม่',
                        ]);
                    }

                    // ยังเป็นสมาชิกของห้องนี้: ไม่เพิ่มซ้ำ
                    if ($membership !== null) {
                        return $room->load('players');
                    }

                    if (
                        $room->room_status !== 'waiting'
                        || $room->game_uuid !== null
                    ) {
                        throw ValidationException::withMessages([
                            'room' => 'ห้องนี้เริ่มเกมแล้ว',
                        ]);
                    }

                    if ($room->players()->where('has_left', false)->count() >= $room->player_limit) {
                        throw ValidationException::withMessages([
                            'room' => 'ห้องเต็มแล้ว',
                        ]);
                    }

                    $room->update(['start_countdown_at' => null]);

                    $room->players()->create([
                        'player_name' => $playerName,
                        'player_uuid' => $playerUuid,
                        'is_host' => false,
                        'is_alive' => true,
                        'is_connected' => true,
                    ]);

                    return $room->load('players');
                }, 3);
            });
    }

    public function leave(string $code, string $playerUuid): void
    {
        $code = strtoupper(trim($code));

        RoomLock::make($code, 10)
            ->block(3, function () use ($code, $playerUuid) {
                DB::transaction(function () use ($code, $playerUuid) {
                    $room = Room::where('room_code', $code)->first();

                    abort_if($room === null, 404, 'ไม่พบห้อง');

                    $player = $room->players()
                        ->where('player_uuid', $playerUuid)
                        ->first();

                    abort_if($player === null, 403, 'คุณไม่ได้อยู่ในห้องนี้');

                    if (
                        $room->room_status !== 'waiting'
                        || $room->game_uuid !== null
                    ) {
                        throw ValidationException::withMessages([
                            'room' => 'ออกจากห้องได้เฉพาะช่วง Lobby',
                        ]);
                    }

                    $wasHost = $player->is_host;

                    $player->delete();
                    $room->update(['start_countdown_at' => null]);

                    $nextPlayer = $room->players()
                        ->orderBy('player_id')
                        ->first();

                    if ($nextPlayer === null) {
                        $room->delete();

                        return;
                    }

                    if ($wasHost) {
                        $nextPlayer->update([
                            'is_host' => true,
                        ]);
                    }
                }, 3);
            });
    }
}
