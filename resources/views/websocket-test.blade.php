<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>WebSocket Test</title>

    @vite([
        'resources/css/app.css',
        'resources/js/websocket.js',
    ])
</head>

<body>
    <div class="p-8">

        <h1 class="text-2xl font-bold">
            WebSocket Test
        </h1>

        <p class="mt-2">
            P2 Reverb / Echo Test
        </p>
    </div>

    <div class="mt-6 p-8">
        <h2>Current Phase</h2>

        <div id="phase" class="text-2xl font-bold">
            waiting...
        </div>
    </div>

    <div class="mt-8 p-8">
        <input
            id="chatInput"
            type="text"
            placeholder="Type a message"
            class="border p-2"
        >

        <button
            id="chatSend"
            class="border px-4 py-2"
        >
            Send
        </button>
    </div>

</body>
</html>