<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;

Route::view('/', 'welcome')->name('home');


// flow = create room by ../room-test  to create room
// to join in another tab ../room-test/{code from created room} 

// enter name in form to create room
Route::get('/room-test', function () {
    return view('room-test');
});

// get name and create room by controller
Route::post('/rooms', [RoomController::class, 'store']);


//  enter name in form  
Route::get('/room-test/{code}', function (string $code) {
    return view('join-room-test', ['code' => $code]);
});

// get name and code to join room by controller
Route::post('/rooms/{code}/join', [RoomController::class, 'join']);



// get room info and view lobby with room info after enter name by create or join
Route::get('/rooms/{code}', [RoomController::class, 'show'])->name('rooms.show');



Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
