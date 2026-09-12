<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Lobby {{ $room['code'] }}</title>
</head>
<body>
    <h1>Room {{ $room['code'] }}</h1>

    <p>Status: {{ $room['status'] }}</p>
    <p>Total player: {{ count($room['players']) }} คน</p>

    <ul>
        @foreach ($room['players'] as $player)
            <li>
                {{ $player['name'] }}

                @if ($player['id'] === $room['host_id'])
                    — Host
                @endif
            </li>
        @endforeach
    </ul>

    <a href="{{ route('rooms.show', ['code' => $room['code']]) }}">
        Room refresh
    </a>
</body>
</html>