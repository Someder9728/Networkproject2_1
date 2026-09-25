<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class GameBroadcastAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'socket_id' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[0-9]+\.[0-9]+\z/',
            ],
            'channel_name' => [
                'required',
                'string',
                'regex:/\Aprivate-rooms\.[A-Z0-9]{6}\z/',
            ],
        ]);

        $playerUuid = $request->session()->get('player_uuid');

        abort_unless(
            is_string($playerUuid) && $playerUuid !== '',
            403,
            'ไม่พบตัวตนผู้เล่น'
        );

        $code = substr(
            $validated['channel_name'],
            strlen('private-rooms.')
        );

        $isMember = Room::where('room_code', $code)
            ->whereHas('players', function ($query) use ($playerUuid) {
                $query->where('player_uuid', $playerUuid)
                    ->where('has_left', false);
            })
            ->exists();

        abort_unless(
            $isMember,
            403,
            'คุณไม่มีสิทธิ์เข้าฟังห้องนี้'
        );

        $authorization = Broadcast::connection('reverb')
            ->validAuthenticationResponse($request, true);

        return response()->json($authorization);
    }
}