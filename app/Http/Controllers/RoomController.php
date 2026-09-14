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
            'host_name' => ['required', 'string', 'max:50'],
        ]);

        $room = $roomService->create(
        $validated['host_name'],
        $this->getPlayerId($request)
        );

        return redirect()->route('rooms.show', ['code' => $room['code'],]);
    }

    public function join(Request $request,RoomService $roomService,string $code): \Illuminate\Http\RedirectResponse {
    $validated = $request->validate([
        'player_name' => ['required', 'string', 'max:50'],
    ]);

    $room = $roomService->join(
        $code,
        $validated['player_name'],
        $this->getPlayerId($request)
        );

    return redirect()->route('rooms.show', ['code' => $room['code'],]);

    }
    
    public function show(string $code, RoomService $roomService)
    {
        $room = $roomService->getRoom($code);

        return view('rooms.lobby', ['room' => $room]);
    }

    private function getPlayerId(Request $request): string
    {
        $playerId = $request->session()->get('player_id');

        if (!$playerId) {
            $playerId = (string) Str::uuid();
            $request->session()->put('player_id', $playerId);
        }

        return $playerId;
    }

    public function leave(
        Request $request,
        RoomService $roomService,
        string $code
        ): \Illuminate\Http\RedirectResponse 
        {
            $playerId = $request->session()->get('player_id');

            abort_unless(
                is_string($playerId) && $playerId !== '',
                403,
                'ไม่พบตัวตนผู้เล่น'
            );

            $roomService->leave($code, $playerId);

            return redirect('/room-test');
}
}