<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เกมห้อง {{ $game['room_code'] }}</title>
</head>
<body>
    <h1>เกมห้อง {{ $game['room_code'] }}</h1>

    <p>
        ระดับ:
        {{ $game['difficulty'] === 'easy' ? 'ง่าย' : 'ยาก' }}
    </p>

    @if ($game['status'] === 'initializing')
        <p>กำลังเตรียมเกม — ยังไม่ได้แจกบทบาท</p>
    @else
        <p>สถานะ: {{ $game['status'] }}</p>
    @endif

    <h2>ผู้เล่น</h2>

    <ul>
        @foreach ($game['players'] as $player)
            <li>
                {{ $player['name'] }}
                — {{ $player['is_alive'] ? 'มีชีวิต' : 'เสียชีวิต' }}
            </li>
        @endforeach
    </ul>

    <a href="{{ route('games.show', ['code' => $game['room_code']]) }}">
        รีเฟรช
    </a>
</body>
</html>