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

    @if ($game['status'] === 'roles_assigned')
        <p>แจกบทบาทแล้ว — รอเริ่มช่วงพูดคุย</p>
    @elseif ($game['status'] === 'initializing')
        <p>กำลังเตรียมเกม</p>
    @else
        <p>สถานะ: {{ $game['status'] }}</p>
    @endif

    @php
        $roleLabels = [
            'werewolf' => 'หมาป่า',
            'seer' => 'ผู้หยั่งรู้',
            'villager' => 'ชาวบ้าน',
        ];
    @endphp

    @if ($game['my_role'] !== null)
        <h2>บทบาทของคุณ</h2>
        <p>{{ $roleLabels[$game['my_role']] ?? 'ไม่ทราบบทบาท' }}</p>
    @endif

    <h2>ผู้เล่น</h2>

    <ul>
    @foreach ($game['players'] as $player)
            <li>
                {{ $player['name'] }}

                @if ($player['player_uuid'] === session('player_uuid'))
                    <strong> (คุณ)</strong>
                @endif

                — {{ $player['is_alive'] ? 'มีชีวิต' : 'เสียชีวิต' }}
            </li>
        @endforeach
    </ul>

    <a href="{{ route('games.show', ['code' => $game['room_code']]) }}">
        รีเฟรช
    </a>

    @foreach ($errors->all() as $error)
        <p>{{ $error }}</p>
    @endforeach

    @if ($game['can_begin_discussion'])
        <form
            method="POST"
            action="{{ route('games.begin-discussion', [
                'code' => $game['room_code'],
            ]) }}"
        >
            @csrf
            <button type="submit">เริ่มช่วงพูดคุย</button>
        </form>
    @endif

    @if ($game['current_phase'] === 'day_discussion')
        <h2>ช่วงพูดคุย — รอบ {{ $game['current_round'] }}</h2>
    @elseif ($game['current_phase'] === 'day_voting')
        <h2>ช่วงโหวต — รอบ {{ $game['current_round'] }}</h2>
        <p>เลือกผู้เล่นที่ต้องการโหวต เปลี่ยนเป้าหมายได้ก่อนหมดเวลา</p>
    @endif

    @if ($game['phase_end_time'] !== null)
        <p>
            เวลาที่เหลือ:
            <strong id="phase-timer">กำลังโหลด...</strong>
        </p>
    @endif

    @if (
        $game['current_phase'] === 'day_discussion'
        && $game['phase_end_time'] !== null
    )
        <form
            id="finish-discussion-form"
            method="POST"
            action="{{ route('games.finish-discussion', [
                'code' => $game['room_code'],
            ]) }}"
        >
            @csrf

            <input
                type="hidden"
                name="expected_end_time"
                value="{{ $game['phase_end_time'] }}"
            >

            <button type="submit">ตรวจเวลาจบช่วงพูดคุย</button>
        </form>
    @endif


    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    @if ($game['can_vote'])
        <form
            method="POST"
            action="{{ route('games.vote', ['code' => $game['room_code']]) }}"
        >
            @csrf

            <input
                type="hidden"
                name="expected_end_time"
                value="{{ $game['phase_end_time'] }}"
            >

            <select name="target_uuid" required>
                <option value="">เลือกผู้เล่น</option>

                @foreach ($game['players'] as $player)
                    @if ($player['is_alive'])
                        <option
                            value="{{ $player['player_uuid'] }}"
                            @selected($game['my_vote'] === $player['player_uuid'])
                        >
                            {{ $player['name'] }}
                        </option>
                    @endif
                @endforeach
            </select>

            <button type="submit">ยืนยันโหวต</button>
        </form>
    @endif

    @if ($game['vote_result'] !== null)
        <h2>ผลโหวตรอบ {{ $game['vote_result']['round'] }}</h2>

        @if ($game['vote_result']['name'] !== null)
            <p>ผู้ถูกโหวตออก: {{ $game['vote_result']['name'] }}</p>

            @if (isset($game['vote_result']['role']))
                <p>
                    บทบาท:
                    {{ $roleLabels[$game['vote_result']['role']] }}
                </p>
            @endif
        @else
            <p>ไม่มีผู้ถูกโหวตออก</p>
        @endif
    @endif

    @if ($game['winner'] !== null)
        <h2>
            {{ $game['winner'] === 'werewolf'
                ? 'ทีมหมาป่าชนะ'
                : 'ทีมชาวบ้านชนะ' }}
        </h2>
    @elseif ($game['current_phase'] === 'night')
        <h2>ช่วงกลางคืน</h2>
        <p>ระบบส่ง Night Action จะเชื่อมในขั้นถัดไป</p>
    @endif

    @if (
        $game['current_phase'] === 'day_voting'
        && $game['phase_end_time'] !== null
    )
        <form
            id="finish-voting-form"
            method="POST"
            action="{{ route('games.finish-voting', [
                'code' => $game['room_code'],
            ]) }}"
        >
            @csrf

            <input
                type="hidden"
                name="expected_end_time"
                value="{{ $game['phase_end_time'] }}"
            >

            <button type="submit">ตรวจเวลาจบช่วงโหวต</button>
        </form>
    @endif


    @if ($game['phase_end_time'] !== null)
        <script>
            const timerElement = document.getElementById('phase-timer');

            if (timerElement) {
                const endTime = Date.parse(
                    @json($game['phase_end_time'])
                );

                const serverTime = Date.parse(
                    @json($game['server_time'])
                );

                const loadedAt = performance.now();
                let timerInterval;

                function updateTimer() {
                    const elapsed = performance.now() - loadedAt;
                    const estimatedServerTime = serverTime + elapsed;

                    const secondsLeft = Math.max(
                        0,
                        Math.ceil((endTime - estimatedServerTime) / 1000)
                    );

                    if (secondsLeft === 0) {
                        clearInterval(timerInterval);

                        const form =
                            document.getElementById('finish-discussion-form')
                            ?? document.getElementById('finish-voting-form');

                        if (form) {
                            timerElement.textContent = 'หมดเวลา — กำลังประมวลผล';
                            form.requestSubmit();
                        } else {
                            timerElement.textContent = 'หมดเวลา';
                        }

                        return;
                    }

                    const minutes = Math.floor(secondsLeft / 60);
                    const seconds = String(secondsLeft % 60).padStart(2, '0');

                    timerElement.textContent = `${minutes}:${seconds}`;
                }

                timerInterval = setInterval(updateTimer, 250);
                updateTimer();
            }
        </script>
    @endif
</body>
</html>