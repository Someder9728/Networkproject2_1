<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('rooms.{room_id}', function () {
    return true;
});

Broadcast::channel('players.{player_id}', function () {
    return true;
});

Broadcast::channel('games.{game_id}.werewolves', function () {
    return true;
});