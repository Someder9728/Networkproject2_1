<?php

// Events
use Illuminate\Http\Request;
use App\Events\TestBroadcastEvent;
use App\Events\ChatMessage;
use App\Events\PhaseChanged;
use Illuminate\Support\Facades\Route;

// Test-broadcast
Route::view('/test-websocket', 'websocket-test')
    ->name('test.websocket');

// phased changed
Route::get('/test-phase/{phase}', function ($phase) {

    broadcast(
        new PhaseChanged(
            'test-room',
            $phase
        )
    );

    return "Phase: {$phase}";
});

// chat
Route::post('/chat', function (Request $request) {
    broadcast(
        new ChatMessage(
            'test-room',
            $request->sender_id,
            $request->sender_name,
            $request->message
        )
    );

    return response()->json([
        'message' => 'Chat sent'
    ]);
});

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
