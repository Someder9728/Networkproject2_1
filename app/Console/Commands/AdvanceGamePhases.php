<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Console\Command;
use Throwable;

class AdvanceGamePhases extends Command
{
    protected $signature = 'game:advance-phases';

    protected $description = 'Advance expired game phases';

    public function handle(RoomService $roomService): int
    {
        $failed = false;

        Room::query()->whereNull('game_uuid')->whereNotNull('start_countdown_at')
            ->where('start_countdown_at', '<=', now())
            ->chunkById(100, function ($rooms) use ($roomService, &$failed) {
                foreach ($rooms as $room) {
                    try {
                        $roomService->advanceLobby($room->room_code);
                    } catch (Throwable $exception) {
                        $failed = true;
                        report($exception);
                    }
                }
            }, 'room_id');

        Room::query()
            ->whereIn('room_status', [
                'day_discussion',
                'day_voting',
                'night',
            ])
            ->whereNotNull('game_uuid')
            ->chunkById(100, function ($rooms) use (
                $roomService,
                &$failed
            ) {
                foreach ($rooms as $room) {
                    try {
                        $roomService->maintainMatch($room->room_code);
                        $roomService->advanceExpiredPhase(
                            $room->room_code
                        );
                    } catch (Throwable $exception) {
                        $failed = true;

                        report($exception);

                        $this->error(
                            "Room {$room->room_code}: "
                            .$exception->getMessage()
                        );
                    }
                }
            }, 'room_id');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
