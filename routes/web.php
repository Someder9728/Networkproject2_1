<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\GameController;


Route::view('/', 'welcome')->name('home');


// flow = create room by ../room-test  to create room
// to join in another tab ../room-test/{code from created room} 

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

//game
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

//disconnect
Route::post(
    '/rooms/{code}/game/debug-disconnect',
    [GameController::class, 'disconnectForDebug']
)->name('games.debug-disconnect');

//le3ave
Route::post(
    '/rooms/{code}/game/leave',
    [GameController::class, 'leaveGame']
)->name('games.leave');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
