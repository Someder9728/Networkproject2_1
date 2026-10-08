<?php

namespace App\Http\Controllers;

use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function guardian(Request $request, RoomService $service, string $code): RedirectResponse
    {
        $uuid = $request->session()->get('player_uuid');
        abort_unless(is_string($uuid) && $uuid !== '', 403);
        $input = $request->validate(['target_uuid' => ['required', 'uuid'], 'expected_end_time' => ['required', 'date']]);
        $service->guardianAction($code, $uuid, $input['target_uuid'], $input['expected_end_time']);

        return redirect()->route('games.show', ['code' => strtoupper($code)]);
    }

    public function trial(Request $request, RoomService $service, string $code): RedirectResponse
    {
        $uuid = $request->session()->get('player_uuid');
        abort_unless(is_string($uuid) && $uuid !== '', 403);
        $input = $request->validate([
            'expected_end_time' => ['required', 'date'],
            'kind' => ['required', 'in:defense,verdict'],
            'value' => ['required', 'string', 'max:500'],
        ]);
        $service->trialAction($code, $uuid, $input['expected_end_time'], $input['kind'], $input['value']);

        return redirect()->route('games.show', ['code' => strtoupper($code)]);
    }

    public function presence(Request $request, RoomService $service, string $code): JsonResponse
    {
        $uuid = $request->session()->get('player_uuid');
        abort_unless(is_string($uuid) && $uuid !== '', 403);
        $service->maintainMatch($code, $uuid);
        $service->advanceExpiredPhase($code);
        $view = $service->getGameView($code, $uuid);

        return response()->json(['sync_version' => $view['sync_version'], 'server_time' => $view['server_time']]);
    }
}
