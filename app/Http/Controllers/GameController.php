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

    public function beginDiscussion(
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

        $roomService->beginDiscussion($code, $playerUuid);

        return redirect()->route('games.show', [
            'code' => strtoupper($code),
        ]);
    }

    public function finishDiscussion(
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

        $validated = $request->validate([
            'expected_end_time' => ['required', 'string', 'date'],
        ]);

        $roomService->finishDiscussion(
            $code,
            $playerUuid,
            $validated['expected_end_time']
        );

        return redirect()->route('games.show', [
            'code' => strtoupper($code),
        ]);
    }

    public function vote(
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

        $validated = $request->validate([
            'target_uuid' => ['required', 'uuid'],
            'expected_end_time' => ['required', 'string', 'date'],
        ]);

        $roomService->vote(
            $code,
            $playerUuid,
            $validated['target_uuid'],
            $validated['expected_end_time']
        );

        return redirect()
            ->route('games.show', ['code' => strtoupper($code)])
            ->with('success', 'บันทึกโหวตแล้ว');
    }

    public function finishVoting(
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

        $validated = $request->validate([
            'expected_end_time' => ['required', 'string', 'date'],
        ]);

        $roomService->finishVoting(
            $code,
            $playerUuid,
            $validated['expected_end_time']
        );

        return redirect()->route('games.show', [
            'code' => strtoupper($code),
        ]);
    }

    public function disconnectForDebug(
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

            $deadline = $roomService->disconnectForDebug(
                $code,
                $playerUuid
            );

            return redirect()->route('rooms.index')->with(
                'success',
                'บันทึกการออกแล้ว เส้นตายกลับเข้าเกม: ' . $deadline
            );
        }

    public function leaveGame(
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

        $roomService->leaveGame($code, $playerUuid);

        if (
            $request->session()->get('current_room_code')
            === strtoupper(trim($code))
        ) {
            $request->session()->forget('current_room_code');
        }

        return redirect()->route('rooms.index')
            ->with('success', 'ออกจากเกมแล้ว คุณสร้างหรือเข้าห้องใหม่ได้');
    }

    public function werewolfAction(
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

        $validated = $request->validate([
            'target_uuid' => ['required', 'uuid'],
            'expected_end_time' => ['required', 'string', 'date'],
        ]);

        $roomService->werewolfAction(
            $code,
            $playerUuid,
            $validated['target_uuid'],
            $validated['expected_end_time']
        );

        return redirect()
            ->route('games.show', ['code' => strtoupper($code)])
            ->with('success', 'บันทึกเป้าหมายแล้ว');
    }

    public function seerAction(
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

        $validated = $request->validate([
            'target_uuid' => ['required', 'uuid'],
            'expected_end_time' => ['required', 'string', 'date'],
        ]);

        $roomService->seerAction(
            $code,
            $playerUuid,
            $validated['target_uuid'],
            $validated['expected_end_time']
        );

        return redirect()
            ->route('games.show', ['code' => strtoupper($code)])
            ->with('success', 'บันทึกคำสั่งตรวจแล้ว รอผลเมื่อจบกลางคืน');
    }

        public function finishNight(
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

        $validated = $request->validate([
            'expected_end_time' => ['required', 'string', 'date'],
        ]);

        $roomService->finishNight(
            $code,
            $playerUuid,
            $validated['expected_end_time']
        );

        return redirect()->route('games.show', [
            'code' => strtoupper($code),
        ]);
    }
}