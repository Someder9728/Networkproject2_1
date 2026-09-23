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

        Room::query()
            ->whereIn('room_status', [
                'day_discussion',
                'day_voting',
                'night',
            ])
            ->whereNotNull('game_uuid')
            ->whereNotNull('room_phase_end_time')
            ->where('room_phase_end_time', '<=', now())
            ->chunkById(100, function ($rooms) use (
                $roomService,
                &$failed
            ) {
                foreach ($rooms as $room) {
                    try {
                        $roomService->advanceExpiredPhase(
                            $room->room_code
                        );
                    } catch (Throwable $exception) {
                        $failed = true;

                        report($exception);

                        $this->error(
                            "Room {$room->room_code}: "
                            . $exception->getMessage()
                        );
                    }
                }
            }, 'room_id');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}