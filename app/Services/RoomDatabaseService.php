<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomDatabaseService
{
    public function create(
        string $hostName,
        string $hostUuid,
        string $difficulty
    ): Room {
        return DB::transaction(function () use (
            $hostName,
            $hostUuid,
            $difficulty
        ) {
            if (Player::where('player_uuid', $hostUuid)->exists()) {
                throw ValidationException::withMessages([
                    'room' => 'คุณมีสมาชิกอยู่ในห้องอื่นแล้ว',
                ]);
            }

            do {
                $code = strtoupper(Str::random(6));
            } while (Room::where('room_code', $code)->exists());

            $room = Room::create([
                'room_code' => $code,
                'room_status' => 'waiting',
                'difficulty' => $difficulty,
            ]);

            $room->players()->create([
                'player_name' => $hostName,
                'player_uuid' => $hostUuid,
                'is_host' => true,
                'is_alive' => true,
                'is_connected' => true,
            ]);

            return $room->load('players');
        });
    }
}