<?php

use App\Services\ChatService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Support/MatchFixtures.php';

it('isolates typing by channel and expires it', function () {
    [, $code, $uuids] = prepareSkipGame('day_discussion');
    $chat = app(ChatService::class);
    $chat->typing($code, $uuids[0], 'werewolf', true);
    expect($chat->typing($code, $uuids[1]))->not->toHaveKey('werewolf');
    $chat->typing($code, $uuids[1], 'all', true);
    expect($chat->typing($code, $uuids[0])['all'])->toBe(['Player 2']);
    $this->travel(8)->seconds();
    expect($chat->typing($code, $uuids[0])['all'])->toBe([]);
});

it('rejects typing in channels the player cannot send to', function () {
    [, $code, $uuids] = prepareSkipGame('night');
    $this->withSession(['player_uuid' => $uuids[1]])->postJson("/rooms/$code/chat/typing", ['channel' => 'werewolf', 'active' => true])->assertForbidden();
});

it('protects voice playback by room and channel without exposing paths', function () {
    Event::fake();
    Storage::fake('local');
    [, $code, $uuids] = prepareSkipGame('day_discussion');
    Storage::disk('local')->put('chat-audio/test.webm', 'private audio');
    app(ChatService::class)->send($code, $uuids[0], 'werewolf', '[ข้อความเสียง]', 'chat-audio/test.webm', 'audio/webm');
    $id = DB::table('chat_messages')->max('chat_id');
    $this->withSession(['player_uuid' => $uuids[1]])->get("/rooms/$code/chat/$id/audio")->assertNotFound();
    $this->withSession(['player_uuid' => $uuids[0]])->get("/rooms/$code/chat/$id/audio")->assertOk()->assertHeader('Content-Type', 'audio/webm');
    $data = app(ChatService::class)->messages($code, $uuids[0]);
    $voice = collect($data['channels'])->firstWhere('name', 'werewolf')['messages'][0];
    expect($voice->audio_url)->toBe("/rooms/$code/chat/$id/audio");
    expect(property_exists($voice, 'audio_path'))->toBeFalse();
    [, $otherCode] = prepareReadyLobby();
    $this->get("/rooms/$otherCode/chat/$id/audio")->assertForbidden();
});

it('rejects oversized and non audio uploads', function () {
    Storage::fake('local');
    [, $code, $uuids] = prepareReadyLobby();
    $this->withSession(['player_uuid' => $uuids[0]])->postJson("/rooms/$code/chat/voice", ['channel' => 'all', 'audio' => UploadedFile::fake()->create('voice.webm', 2049, 'audio/webm')])->assertUnprocessable();
    $this->postJson("/rooms/$code/chat/voice", ['channel' => 'all', 'audio' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')])->assertUnprocessable();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('saves a voice upload and cleans up an unauthorized upload', function () {
    Event::fake();
    Storage::fake('local');
    [, $code, $uuids] = prepareReadyLobby();
    $this->withSession(['player_uuid' => $uuids[0]])->postJson("/rooms/$code/chat/voice", ['channel' => 'all', 'audio' => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm')])->assertCreated();
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    $this->postJson("/rooms/$code/chat/voice", ['channel' => 'werewolf', 'audio' => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm')])->assertForbidden();
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});
