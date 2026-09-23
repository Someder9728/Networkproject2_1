<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Exceptions\GameSnapshotUnavailableException;
use App\Models\Room;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (
            GameSnapshotUnavailableException $exception,
            Request $request
        ) {
            $playerUuid = $request->hasSession()
                ? $request->session()->get('player_uuid')
                : null;

            $isMember = is_string($playerUuid)
                && $playerUuid !== ''
                && Room::where('room_code', $exception->roomCode)
                    ->whereHas('players', function ($query) use ($playerUuid) {
                        $query->where('player_uuid', $playerUuid)
                            ->where('has_left', false);
                    })
                    ->exists();

            if (!$isMember) {
                return response('คุณไม่ได้เป็นสมาชิกปัจจุบันของห้องนี้', 403);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'code' => 'GAME_SNAPSHOT_UNAVAILABLE',
                    'message' => 'ข้อมูลเกมไม่พร้อมใช้งาน กรุณาออกจากห้องนี้',
                    'room_code' => $exception->roomCode,
                ], 409);
            }

            return response()->view('rooms.unavailable', [
                'code' => $exception->roomCode,
            ], 409);
        });
    })->create();
