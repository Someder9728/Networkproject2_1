<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private function playerUuid(Request $request): string
    {
        $uuid = $request->session()->get('player_uuid');

        abort_unless(
            is_string($uuid) && $uuid !== '',
            403,
            'ไม่พบตัวตนผู้เล่น'
        );

        return $uuid;
    }

    public function index(
        Request $request,
        ChatService $chat,
        string $code
    ): JsonResponse {
        return response()
            ->json($chat->messages($code, $this->playerUuid($request)))
            ->header('Cache-Control', 'no-store');
    }

    public function store(
        Request $request,
        ChatService $chat,
        string $code
    ): JsonResponse {
        $uuid = $this->playerUuid($request);

        if (is_string($request->input('message'))) {
            $request->merge([
                'message' => trim($request->input('message')),
            ]);
        }

        $validated = $request->validate([
            'channel' => ['required', 'in:all,werewolf,dead'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $chat->send(
            $code,
            $uuid,
            $validated['channel'],
            $validated['message']
        );

        return response()->json(['saved' => true], 201);
    }
}