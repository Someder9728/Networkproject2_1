<?php

namespace App\Http\Controllers;

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

        $room = $roomService->create($validated['host_name']);

        return redirect()->route('rooms.show', ['code' => $room['code'],]);
    }

    public function join(Request $request,RoomService $roomService,string $code): \Illuminate\Http\RedirectResponse {
    $validated = $request->validate([
        'player_name' => ['required', 'string', 'max:50'],
    ]);

    $room = $roomService->join(
        $code,
        $validated['player_name']
    );

    return redirect()->route('rooms.show', ['code' => $room['code'],]);

    }
    
    public function show(string $code, RoomService $roomService)
    {
        $room = $roomService->getRoom($code);

        return view('rooms.lobby', ['room' => $room]);
    }
}