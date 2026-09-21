<?php

namespace App\Http\Controllers;

use App\Services\RoomService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public function show(
        Request $request,
        RoomService $roomService,
        string $code
    ): View {
        $playerUuid = $request->session()->get('player_uuid');

        abort_unless(
            is_string($playerUuid) && $playerUuid !== '',
            403,
            'ไม่พบตัวตนผู้เล่น'
        );

        $game = $roomService->getGameView($code, $playerUuid);

        return view('games.show', ['game' => $game]);
    }
}