<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Lobby {{ $room['code'] }}</title>
</head>
<body>
    <h1>Room {{ $room['code'] }}</h1>

    <p>Status: {{ $room['status'] }}</p>
    <p>
        ระดับ:
        {{ $room['difficulty'] === 'easy' ? 'ง่าย' : 'ยาก' }}
    </p>

    <p>
        {{ $room['difficulty'] === 'easy'
            ? 'ง่ายจัด โหมดเดกอนุบาน'
            : 'ยาก กัววววววๆๆๆๆ' }}
    </p>
    <p>Total player: {{ count($room['players']) }} คน</p>

    <ul>
        @foreach ($room['players'] as $player)
            <li>
                {{ $player['name'] }}

                @if ($player['player_uuid'] === session('player_uuid'))
                    <strong> (คุณ)</strong>
                @endif

                @if ($player['player_uuid'] === $room['host_uuid'])
                    — Host
                @endif
            </li>
        @endforeach
    </ul>

    <a href="{{ route('rooms.show', ['code' => $room['code']]) }}">
        รีเฟรช
    </a>

    @if (
        $room['status'] === 'waiting'
        && collect($room['players'])->contains(
            'player_uuid',
            session('player_uuid')
        )
    )
    <form method="POST" action="{{ route('rooms.leave', ['code' => $room['code']]) }}">
        @csrf

        <button type="submit">ออกจากห้อง</button>
    </form>
    @endif

    @foreach ($errors->all() as $error)
        <p>{{ $error }}</p>
    @endforeach


    @if (
    $room['status'] === 'waiting'
    && session('player_uuid') === $room['host_uuid']
)
    <form
        method="POST"
        action="{{ route('rooms.start', ['code' => $room['code']]) }}"
    >
        @csrf

        <button type="submit">เริ่มเกม</button>
    </form>
    @endif

    @if (
        ($room['game_uuid'] ?? null) !== null
        && collect($room['players'])->contains(
            'player_uuid',
            session('player_uuid')
        )
    )
        <p>Game Session: {{ $room['game_uuid'] }}</p>
        <p>กำลังเตรียมเกม — รอเชื่อมระบบแจก Role</p>
        <a href="{{ route('games.show', ['code' => $room['code']]) }}">
            เข้าหน้าเกม
        </a>
    @endif
</body>
</html>