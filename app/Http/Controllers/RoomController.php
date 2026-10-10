<?php

namespace App\Http\Controllers;

use App\GameLogic\DifficultyConfig;
use App\Services\GameService;
use App\Services\RoomService;
use App\Support\AvatarCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function store(
        Request $request,
        RoomService $roomService
    ): RedirectResponse {
        $validated = $request->validate([
            'host_name' => ['required', 'string', 'max:45'],
            'difficulty' => ['required', 'in:easy,hard'],
            'player_limit' => ['required', 'integer', Rule::in(DifficultyConfig::supportedPlayerCounts())],
            'game_mode' => ['sometimes', 'in:normal,short'],
        ]);

        $room = $roomService->create(
            $validated['host_name'],
            $this->getPlayerUuid($request),
            $validated['difficulty'],
            (int) $validated['player_limit'],
            $validated['game_mode'] ?? 'normal'
        );

        $request->session()->put('current_room_code', $room['code']);

        return redirect()->route('rooms.show', [
            'code' => $room['code'],
        ]);
    }

    public function ready(Request $request, RoomService $roomService, string $code): RedirectResponse
    {
        $playerUuid = $request->session()->get('player_uuid');
        abort_unless(is_string($playerUuid) && $playerUuid !== '', 403);
        $request->validate(['ready' => ['required', 'boolean']]);
        $roomService->setReady($code, $playerUuid, $request->boolean('ready'));

        return redirect()->route('rooms.show', ['code' => strtoupper($code)]);
    }

    public function avatar(Request $request, RoomService $rooms, string $code): RedirectResponse
    {
        $uuid = $request->session()->get('player_uuid');
        abort_unless(is_string($uuid) && $uuid !== '', 403);
        $avatar = $request->validate([
            'character' => ['required', Rule::in(array_keys(AvatarCatalog::CHARACTERS))],
            'face' => ['required', Rule::in(array_keys(AvatarCatalog::FACES))],
            'accessory' => ['required', Rule::in(array_keys(AvatarCatalog::ACCESSORIES))],
            'color' => ['required', Rule::in(array_keys(AvatarCatalog::COLORS))],
        ]);
        $rooms->updateAvatar($code, $uuid, $avatar);

        return redirect()->route('rooms.show', ['code' => strtoupper($code)])->with('success', 'บันทึกหน้าตาแล้ว กดพร้อมเมื่อพร้อมเล่น');
    }

    public function advanceStart(Request $request, RoomService $roomService, string $code): JsonResponse
    {
        $playerUuid = $request->session()->get('player_uuid');
        abort_unless(is_string($playerUuid) && $playerUuid !== '', 403);
        $room = $roomService->getRoom($code);
        abort_unless(collect($room['players'])->contains('player_uuid', $playerUuid), 403);
        $roomService->advanceLobby($code);
        $room = $roomService->getRoom($code);

        return response()->json([
            'game_url' => $room['game_uuid'] !== null ? route('games.show', ['code' => $room['code']]) : null,
            'start_countdown_at' => $room['start_countdown_at'],
            'server_time' => $room['server_time'],
            'players' => $room['players'],
        ]);
    }

    public function join(Request $request, RoomService $roomService, string $code): RedirectResponse
    {
        $validated = $request->validate([
            'player_name' => ['required', 'string', 'max:50'],
        ]);

        $room = $roomService->join(
            $code,
            $validated['player_name'],
            $this->getPlayerUuid($request)
        );

        $request->session()->put('current_room_code', $room['code']);

        return redirect()->route('rooms.show', ['code' => $room['code']]);

    }

    public function show(
        Request $request,
        RoomService $roomService,
        string $code
    ): View|RedirectResponse {
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
    ): RedirectResponse {
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
    ): RedirectResponse {
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
    ): RedirectResponse {
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

        if (! $playerUuid) {
            $playerUuid = (string) Str::uuid();
            $request->session()->put('player_uuid', $playerUuid);
        }

        return $playerUuid;
    }

    public function index(
        Request $request,
        RoomService $roomService
    ): View {
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
