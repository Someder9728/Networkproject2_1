<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\GameBroadcastAuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// enter name in form to create room
Route::get('/room-test', [RoomController::class, 'index'])
    ->name('rooms.index');

Route::post('/join-room', [RoomController::class, 'joinFromForm'])
    ->name('rooms.join-form');

// get name and create room by controller
Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');

//  enter name in form
Route::get('/room-test/{code}', function (string $code) {
    return view('join-room-test', ['code' => $code]);
});

// get name and code to join room by controller
Route::post('/rooms/{code}/join', [RoomController::class, 'join']);

// get room info and view lobby with room info after enter name by create or join
Route::get('/rooms/{code}', [RoomController::class, 'show'])->name('rooms.show');

// to leave
Route::post('/rooms/{code}/leave', [RoomController::class, 'leave'])
    ->name('rooms.leave');

// start
Route::post('/rooms/{code}/start', [RoomController::class, 'start'])
    ->name('rooms.start');

Route::post('/rooms/{code}/ready', [RoomController::class, 'ready'])->name('rooms.ready');
Route::post('/rooms/{code}/advance-start', [RoomController::class, 'advanceStart'])->name('rooms.advance-start');
Route::post('/rooms/{code}/game/skip-discussion', [GameController::class, 'skipDiscussion'])->name('games.skip-discussion');

Route::post('/rooms/{code}/game/guardian-action', [MatchController::class, 'guardian'])->name('games.guardian-action');
Route::post('/rooms/{code}/game/presence', [MatchController::class, 'presence'])->name('games.presence');

// game
Route::get('/rooms/{code}/game', [GameController::class, 'show'])
    ->name('games.show');

Route::post(
    '/rooms/{code}/game/begin-discussion',
    [GameController::class, 'beginDiscussion']
)->name('games.begin-discussion');

Route::post(
    '/rooms/{code}/game/finish-discussion',
    [GameController::class, 'finishDiscussion']
)->name('games.finish-discussion');

Route::post('/rooms/{code}/game/vote', [GameController::class, 'vote'])
    ->name('games.vote');

Route::post(
    '/rooms/{code}/game/finish-voting',
    [GameController::class, 'finishVoting']
)->name('games.finish-voting');

// disconnect for debug
// Route::post(
//     '/rooms/{code}/game/debug-disconnect',
//     [GameController::class, 'disconnectForDebug']
// )->name('games.debug-disconnect');

// le3ave
Route::post(
    '/rooms/{code}/game/leave',
    [GameController::class, 'leaveGame']
)->name('games.leave');

// in case leave
Route::post(
    '/rooms/{code}/game/leave-unavailable',
    [GameController::class, 'leaveUnavailableGame']
)->name('games.leave-unavailable');

// action
Route::post(
    '/rooms/{code}/game/werewolf-action',
    [GameController::class, 'werewolfAction']
)->name('games.werewolf-action');

Route::post(
    '/rooms/{code}/game/seer-action',
    [GameController::class, 'seerAction']
)->name('games.seer-action');

// fin nigth
Route::post(
    '/rooms/{code}/game/finish-night',
    [GameController::class, 'finishNight']
)->name('games.finish-night');

// broadcast
Route::post('/game-broadcast/auth', GameBroadcastAuthController::class)
    ->middleware('throttle:game-broadcast-auth')
    ->name('games.broadcast-auth');

Route::get('/rooms/{code}/chat', [ChatController::class, 'index'])
    ->middleware('throttle:game-chat-read')
    ->name('rooms.chat.index');

Route::post('/rooms/{code}/chat', [ChatController::class, 'store'])
    ->middleware('throttle:game-chat-send')
    ->name('rooms.chat.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::post('/rooms/{code}/game/trial', [MatchController::class, 'trial'])->name('games.trial');

Route::match(['get', 'post'], '/rooms/{code}/chat/typing', [ChatController::class, 'typing'])->middleware('throttle:game-chat-read');
Route::post('/rooms/{code}/chat/voice', [ChatController::class, 'voice'])->middleware('throttle:game-chat-send');
Route::get('/rooms/{code}/chat/{message}/audio', [ChatController::class, 'audio'])->whereNumber('message')->middleware('throttle:game-chat-read')->name('rooms.chat.audio');

Route::post('/rooms/{code}/avatar', [RoomController::class, 'avatar'])->middleware('throttle:game-chat-send')->name('rooms.avatar');
