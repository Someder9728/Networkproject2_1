<?php

use Illuminate\Support\Facades\Route;

Route::view('/lobby', 'game.lobby')->name('game.lobby');
Route::view('/create-room', 'game.create-room')->name('game.create-room');
Route::view('/join-room', 'game.join-room')->name('game.join-room');
Route::view('/player-list', 'game.player-list')->name('game.player-list');

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';