<?php

namespace App\Http\Controllers;


use Illuminate\Support\Str;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function store(
            Request $request,
            RoomService $roomService
        ): \Illuminate\Http\RedirectResponse {
            $validated = $request->validate([
                'host_name' => ['required', 'string', 'max:45'],
                'difficulty' => ['required', 'in:easy,hard'],
            ]);

            $room = $roomService->create(
                $validated['host_name'],
                $this->getPlayerUuid($request),
                $validated['difficulty']
            );

            return redirect()->route('rooms.show', [
                'code' => $room['code'],
            ]);
        }

    public function join(Request $request,RoomService $roomService,string $code): \Illuminate\Http\RedirectResponse {
    $validated = $request->validate([
        'player_name' => ['required', 'string', 'max:50'],
    ]);

    $room = $roomService->join(
        $code,
        $validated['player_name'],
        $this->getPlayerUuid($request)
        );

    return redirect()->route('rooms.show', ['code' => $room['code'],]);

    }
    
    public function show(string $code, RoomService $roomService)
    {
        $room = $roomService->getRoom($code);

        return view('rooms.lobby', ['room' => $room]);
    }

    private function getPlayerUuid(Request $request): string
    {
        $playerUuid = $request->session()->get('player_uuid');

        if (!$playerUuid) {
            $playerUuid = (string) Str::uuid();
            $request->session()->put('player_uuid', $playerUuid);
        }

        return $playerUuid;
    }

    public function leave(
        Request $request,
        RoomService $roomService,
        string $code
    ): \Illuminate\Http\RedirectResponse {
        $playerUuid = $request->session()->get('player_uuid');

        abort_unless(
            is_string($playerUuid) && $playerUuid !== '',
            403,
            'ไม่พบตัวตนผู้เล่น'
        );

        $roomService->leave($code, $playerUuid);

        return redirect('/room-test');
    }
}