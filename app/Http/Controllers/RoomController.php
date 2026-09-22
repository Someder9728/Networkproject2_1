<?php

namespace App\Http\Controllers;


use Illuminate\Support\Str;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\GameService;

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

            $request->session()->put('current_room_code', $room['code']);

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

    $request->session()->put('current_room_code', $room['code']);

    return redirect()->route('rooms.show', ['code' => $room['code'],]);

    }
    
    public function show(
        Request $request,
        RoomService $roomService,
        string $code
    ): \Illuminate\View\View|\Illuminate\Http\RedirectResponse {
        $playerUuid = $request->session()->get('player_uuid');

        abort_unless(
            is_string($playerUuid) && $playerUuid !== '',
            403,
            'กรุณาเข้าร่วมห้องก่อน'
        );

        $room = $roomService->getRoom($code);

        $isMember = collect($room['players'])
            ->contains('player_uuid', $playerUuid);

        abort_unless(
            $isMember,
            403,
            'คุณไม่ได้เป็นสมาชิกห้องนี้'
        );

        if (($room['game_uuid'] ?? null) !== null) {
            return redirect()->route('games.show', [
                'code' => $room['code'],
            ]);
        }

        return view('rooms.lobby', ['room' => $room]);
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

            if ($request->session()->get('current_room_code') === strtoupper($code)) {
                $request->session()->forget('current_room_code');
            }

            return redirect('/room-test');
        }

    public function start(
        Request $request,
        RoomService $roomService,
        GameService $gameService,
        string $code
    ): \Illuminate\Http\RedirectResponse {
        $playerUuid = $request->session()->get('player_uuid');

        abort_unless(
            is_string($playerUuid) && $playerUuid !== '',
            403,
            'ไม่พบตัวตนผู้เล่น'
        );

        $room = $roomService->start(
            $code,
            $playerUuid,
            $gameService
        );

        return redirect()->route('rooms.show', [
            'code' => $room['code'],
        ]);
    }

    public function joinFromForm(
        Request $request,
        RoomService $roomService
    ): \Illuminate\Http\RedirectResponse {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6', 'alpha_num:ascii'],
            'player_name' => ['required', 'string', 'max:45'],
        ]);

        $room = $roomService->join(
            $validated['code'],
            $validated['player_name'],
            $this->getPlayerUuid($request)
        );

        $request->session()->put('current_room_code', $room['code']);

        return redirect()->route('rooms.show', [
            'code' => $room['code'],
        ]);
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

    public function index(
        Request $request,
        RoomService $roomService
    ): \Illuminate\View\View {
        $currentRoom = null;
        $code = $request->session()->get('current_room_code');
        $playerUuid = $request->session()->get('player_uuid');

        if (is_string($code) && is_string($playerUuid)) {
            $currentRoom = $roomService->findRoomForPlayer(
                $code,
                $playerUuid
            );
        }

        if ($currentRoom === null) {
            $request->session()->forget('current_room_code');
        }

        return view('room-test', [
            'currentRoom' => $currentRoom,
        ]);
    }


}