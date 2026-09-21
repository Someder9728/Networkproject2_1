<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Werewolf — สร้างหรือเข้าห้อง</title>
</head>
<body>
    <h1>Werewolf</h1>

    @if ($currentRoom !== null)
        <section>
            <p>ห้องล่าสุดของคุณ: {{ $currentRoom['code'] }}</p>

            <a href="{{ route('rooms.show', ['code' => $currentRoom['code']]) }}">
                {{ $currentRoom['game_uuid'] !== null
                    ? 'กลับเข้าเกม'
                    : 'กลับ Lobby' }}
            </a>
        </section>
    @endif

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <section>
        <h2>สร้างห้อง</h2>

        <form method="POST" action="{{ route('rooms.store') }}">
            @csrf

            <p>
                <label>
                    ชื่อของคุณ
                    <input
                        name="host_name"
                        value="{{ old('host_name') }}"
                        required
                        maxlength="45"
                    >
                </label>
            </p>

            <p>
                <label>
                    ระดับความยาก
                    <select name="difficulty" required>
                        <option
                            value="easy"
                            @selected(old('difficulty', 'easy') === 'easy')
                        >
                            ง่าย — เปิดเผย Role ของคนตาย
                        </option>

                        <option
                            value="hard"
                            @selected(old('difficulty') === 'hard')
                        >
                            ยาก — ไม่เปิดเผย Role ของคนตาย
                        </option>
                    </select>
                </label>
            </p>

            <button type="submit">สร้างห้อง</button>
        </form>
    </section>

    <hr>

    <section>
        <h2>เข้าห้อง</h2>

        <form method="POST" action="{{ route('rooms.join-form') }}">
            @csrf

            <p>
                <label>
                    ชื่อของคุณ
                    <input
                        name="player_name"
                        value="{{ old('player_name') }}"
                        required
                        maxlength="45"
                    >
                </label>
            </p>

            <p>
                <label>
                    รหัสห้อง
                    <input
                        name="code"
                        value="{{ old('code') }}"
                        required
                        minlength="6"
                        maxlength="6"
                        pattern="[A-Za-z0-9]{6}"
                        placeholder="ABC123"
                    >
                </label>
            </p>

            <button type="submit">เข้าห้อง</button>
        </form>
    </section>
</body>
</html>