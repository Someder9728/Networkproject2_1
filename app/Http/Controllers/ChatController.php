<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function typing(Request $request, ChatService $chat, string $code): JsonResponse
    {
        $data = $request->isMethod('post') ? $request->validate(['channel' => 'required|in:all,werewolf,dead', 'active' => 'required|boolean']) : [];

        return response()->json(['typing' => $chat->typing($code, $this->playerUuid($request), $data['channel'] ?? null, $data['active'] ?? false)])->header('Cache-Control', 'no-store');
    }

    public function voice(Request $request, ChatService $chat, string $code): JsonResponse
    {
        $uuid = $this->playerUuid($request);
        $data = $request->validate(['channel' => 'required|in:all,werewolf,dead', 'audio' => 'required|file|mimetypes:audio/webm,video/webm,audio/ogg,video/ogg,audio/mp4,video/mp4|max:2048']);
        $file = $request->file('audio');
        $path = $file->store('chat-audio', 'local');
        try {
            $chat->send($code, $uuid, $data['channel'], '[ข้อความเสียง]', $path, $file->getMimeType());
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json(['saved' => true], 201);
    }

    public function audio(Request $request, ChatService $chat, string $code, int $message): BinaryFileResponse
    {
        $audio = $chat->audio($code, $this->playerUuid($request), $message);
        $path = Storage::disk('local')->path($audio->audio_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => $audio->audio_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
