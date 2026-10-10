<?php

use App\Events\ChatUpdated;
use App\Models\Player;
use App\Models\Room;
use App\Services\ChatService;
use App\Support\AvatarCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/MatchFixtures.php';

it('saves only validated cosmetics for the current player and carries them into the match', function () {
    Event::fake();
    [$rooms, $code, $uuids] = prepareReadyLobby();
    $look = ['character' => 'cat', 'face' => 'wink', 'accessory' => 'flower', 'color' => 'mint'];
    $this->withSession(['player_uuid' => $uuids[1]])->post("/rooms/$code/avatar", $look + ['role' => 'werewolf', 'player_uuid' => $uuids[0]])->assertRedirect();
    expect(Player::where('player_uuid', $uuids[1])->first()->avatar)->toBe($look);
    expect(Player::where('player_uuid', $uuids[0])->first()->avatar)->toBeNull();
    foreach ($uuids as $uuid) {
        $rooms->setReady($code, $uuid, true);
    }
    Room::where('room_code', $code)->update(['start_countdown_at' => now()->subSecond()]);
    $rooms->advanceLobby($code);
    $snapshot = Room::where('room_code', $code)->first()->game_snapshot;
    expect(collect($snapshot['players'])->firstWhere('player_uuid', $uuids[1])['avatar'])->toBe($look);
    $view = $rooms->getGameView($code, $uuids[1]);
    expect($view['players'][1]['avatar'])->toBe($look)->and($view['players'][0])->not->toHaveKey('role');
    $roles = array_column($snapshot['players'], 'role');
    $this->postJson("/rooms/$code/avatar", $look)->assertUnprocessable();
    expect(array_column(Room::where('room_code', $code)->first()->game_snapshot['players'], 'role'))->toBe($roles);
});

it('rejects unknown looks, outsiders and changes after ready', function () {
    Event::fake();
    [$rooms, $code, $uuids] = prepareReadyLobby();
    $look = AvatarCatalog::normalize(null);
    $this->withSession(['player_uuid' => $uuids[0]])->postJson("/rooms/$code/avatar", array_replace($look, ['character' => '<script>']))->assertUnprocessable();
    $rooms->setReady($code, $uuids[0], true);
    $this->postJson("/rooms/$code/avatar", $look)->assertUnprocessable();
    $this->withSession(['player_uuid' => (string) Str::uuid()])->postJson("/rooms/$code/avatar", $look)->assertForbidden();
});

it('renders the table and outfit picker for a lobby and table actions for ten players', function () {
    Event::fake();
    [, $code, $uuids] = prepareReadyLobby(10);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[0]])->get("/rooms/$code")->assertOk()->assertSee('avatar-picker')->assertSee('lobby-table')->assertSee('หน้าตาไม่เกี่ยวกับบทบาท');
    [, $code, $uuids] = prepareSkipGame('day_voting', 10);
    $this->withSession(['player_uuid' => $uuids[1]])->get("/rooms/$code/game")->assertOk()->assertSee('table-seats')->assertSee('ตอนนี้ทำอะไร?')->assertSee('seat-'.$uuids[0])->assertSee('name="target_uuid" value="'.$uuids[0].'"', false);
});

it('shows whispers only to their sender and recipient in the all channel', function () {
    Event::fake();
    [, $code, $uuids] = prepareSkipGame('day_discussion');
    $this->withSession(['player_uuid' => $uuids[0]])->postJson("/rooms/$code/chat", ['channel' => 'all', 'message' => 'secret <script>', 'recipient_uuid' => $uuids[1]])->assertCreated();
    $chat = app(ChatService::class);
    foreach ([$uuids[0], $uuids[1]] as $uuid) {
        $data = $chat->messages($code, $uuid);
        $message = $data['channels'][0]['messages'][0];
        expect($message->content)->toBe('secret <script>')->and($message->is_private)->toBeTrue()->and($message->recipient_uuid)->toBe($uuids[1]);
    }
    expect($chat->messages($code, $uuids[2])['channels'][0]['messages'])->toHaveCount(0);
    expect((new ChatUpdated($code))->broadcastWith())->toBe(['room_code' => $code]);
    $this->postJson("/rooms/$code/chat", ['channel' => 'all', 'message' => 'public'])->assertCreated();
    expect($chat->messages($code, $uuids[2])['channels'][0]['messages'])->toHaveCount(1);
});

it('rejects self, another room and role channels as whisper recipients', function () {
    Event::fake();
    [, $code, $uuids] = prepareSkipGame('day_discussion');
    [, , $others] = prepareReadyLobby();
    $this->withSession(['player_uuid' => $uuids[0]]);
    foreach ([$uuids[0], $others[0], (string) Str::uuid()] as $uuid) {
        $this->postJson("/rooms/$code/chat", ['channel' => 'all', 'message' => 'no', 'recipient_uuid' => $uuid])->assertForbidden();
    }
    $this->postJson("/rooms/$code/chat", ['channel' => 'werewolf', 'message' => 'no', 'recipient_uuid' => $uuids[1]])->assertForbidden();
    expect(DB::table('chat_messages')->count())->toBe(0);
});

it('keeps night and dead player communication rules for whispers', function () {
    Event::fake();
    [, $code, $uuids] = prepareSkipGame('night');
    $chat = app(ChatService::class);
    expect($chat->messages($code, $uuids[0])['recipients'])->toBe([]);
    $this->withSession(['player_uuid' => $uuids[0]])->postJson("/rooms/$code/chat", ['channel' => 'all', 'message' => 'no', 'recipient_uuid' => $uuids[1]])->assertForbidden();
    $record = Room::where('room_code', $code)->first();
    $game = $record->game_snapshot;
    $game['players'][1]['is_alive'] = false;
    $game['players'][2]['is_alive'] = false;
    $record->update(['game_snapshot' => $game]);
    $this->withSession(['player_uuid' => $uuids[1]])->postJson("/rooms/$code/chat", ['channel' => 'all', 'message' => 'ghost', 'recipient_uuid' => $uuids[2]])->assertCreated();
    $this->postJson("/rooms/$code/chat", ['channel' => 'all', 'message' => 'ghost', 'recipient_uuid' => $uuids[0]])->assertForbidden();
    $this->postJson("/rooms/$code/chat", ['channel' => 'all', 'message' => 'ghost'])->assertForbidden();
    expect($chat->messages($code, $uuids[0])['channels'][0]['messages'])->toHaveCount(0);
    expect($chat->messages($code, $uuids[2])['channels'][0]['messages'])->toHaveCount(1);
});

it('keeps whisper typing private even in the all channel', function () {
    [, $code, $uuids] = prepareSkipGame('day_discussion');
    $chat = app(ChatService::class);
    $chat->typing($code, $uuids[0], 'all', true, $uuids[1]);
    expect($chat->typing($code, $uuids[1])['private'])->toBe([['uuid' => $uuids[0], 'name' => 'Player 1']]);
    expect($chat->typing($code, $uuids[2])['private'])->toBe([])->and($chat->typing($code, $uuids[2])['all'])->toBe([]);
    $this->travel(8)->seconds();
    expect($chat->typing($code, $uuids[1])['private'])->toBe([]);
});

it('protects whispered voice and its file from other players', function () {
    Event::fake();
    Storage::fake('local');
    [, $code, $uuids] = prepareSkipGame('day_discussion');
    $this->withSession(['player_uuid' => $uuids[0]])->postJson("/rooms/$code/chat/voice", ['channel' => 'all', 'recipient_uuid' => $uuids[1], 'audio' => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm')])->assertCreated();
    $id = DB::table('chat_messages')->max('chat_id');
    $this->withSession(['player_uuid' => $uuids[1]])->get("/rooms/$code/chat/$id/audio")->assertOk();
    $this->withSession(['player_uuid' => $uuids[2]])->get("/rooms/$code/chat/$id/audio")->assertNotFound();
    expect(app(ChatService::class)->messages($code, $uuids[2])['channels'][0]['messages'])->toHaveCount(0);
});
