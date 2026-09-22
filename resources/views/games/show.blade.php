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

    @if ($game['my_role'] === 'werewolf')
    <h2>หมาป่าร่วมทีม</h2>

    @forelse ($game['werewolf_teammates'] as $teammate)
            <p>
                {{ $teammate['name'] }}

                @if ($teammate['has_left'])
                    — ออกจากเกมแล้ว
                @elseif (!$teammate['is_alive'])
                    — เสียชีวิต
                @else
                    — มีชีวิต
                @endif
            </p>
        @empty
            <p>คุณเป็นหมาป่าเพียงคนเดียว</p>
        @endforelse
    @endif
 
    <h2>ผู้เล่น</h2>

    <ul>
        @foreach ($game['players'] as $player)
            <li>
                {{ $player['name'] }}

                @if ($player['player_uuid'] === session('player_uuid'))
                    <strong> (คุณ)</strong>
                @endif

                @if ($player['has_left'])
                    — ออกจากเกมแล้ว
                @elseif (!$player['is_alive'])
                    — เสียชีวิต
                @else
                    — มีชีวิต
                @endif
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

        <p>การโหวตครั้งที่ {{ $game['ballot_number'] }}</p>

        @if ($game['ballot_number'] === 2)
            <p>คะแนนครั้งแรกเสมอ จึงเปิดโหวตใหม่อีก 1 ครั้ง</p>
        @endif


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
                    @if ($player['is_alive'] && !$player['has_left'])
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


    <form
        method="POST"
        action="{{ route('games.leave', ['code' => $game['room_code']]) }}"
        onsubmit="return confirm('ออกถาวรจากเกมนี้? คุณจะกลับเข้าห้องเดิมไม่ได้');"
    >
        @csrf
        <button type="submit">ออกจากเกมถาวร</button>
    </form>

    @if ($game['can_werewolf_act'])
        <h2>เลือกเป้าหมายคืนนี้</h2>

        <form
            method="POST"
            action="{{ route('games.werewolf-action', [
                'code' => $game['room_code'],
            ]) }}"
        >
            @csrf

            <input
                type="hidden"
                name="expected_end_time"
                value="{{ $game['phase_end_time'] }}"
            >

            <select name="target_uuid" required>
                <option value="">เลือกผู้เล่น</option>

                @foreach ($game['werewolf_targets'] as $target)
                    <option
                        value="{{ $target['player_uuid'] }}"
                        @selected(
                            $game['my_night_target'] === $target['player_uuid']
                        )
                    >
                        {{ $target['name'] }}
                    </option>
                @endforeach
            </select>

            <button type="submit">ยืนยันเป้าหมาย</button>
        </form>
    @endif

    @if ($game['my_role'] === 'seer')
        <p>
            ใช้สิทธิ์ตรวจแล้ว {{ $game['my_seer_checks_used'] }} ครั้ง
            / {{ $game['my_seer_checks_limit'] ?? 'ไม่จำกัด' }}
        </p>
    @endif

    @if ($game['can_seer_act'])
        <h2>เลือกผู้เล่นที่จะตรวจคืนนี้</h2>

        <form
            method="POST"
            action="{{ route('games.seer-action', [
                'code' => $game['room_code'],
            ]) }}"
        >
            @csrf

            <input
                type="hidden"
                name="expected_end_time"
                value="{{ $game['phase_end_time'] }}"
            >

            <select name="target_uuid" required>
                <option value="">เลือกผู้เล่น</option>

                @foreach ($game['seer_targets'] as $target)
                    <option
                        value="{{ $target['player_uuid'] }}"
                        @selected(
                            $game['my_night_target'] === $target['player_uuid']
                        )
                    >
                        {{ $target['name'] }}
                    </option>
                @endforeach
            </select>

            <button type="submit">ยืนยันการตรวจ</button>
        </form>
    @endif


    @if ($game['night_result'] !== null)
        <h2>ผลกลางคืนรอบ {{ $game['night_result']['round'] }}</h2>

        @if ($game['night_result']['name'] !== null)
            <p>ผู้เสียชีวิต: {{ $game['night_result']['name'] }}</p>

            @if (isset($game['night_result']['role']))
                <p>
                    บทบาท:
                    {{ $roleLabels[$game['night_result']['role']] }}
                </p>
            @endif
        @else
            <p>คืนนี้ไม่มีผู้เสียชีวิตจากหมาป่า</p>
        @endif
    @endif

    @if ($game['my_role'] === 'seer')
        <h2>ผลตรวจของคุณ</h2>

        @forelse ($game['my_seer_results'] as $result)
            <p>
                รอบ {{ $result['round'] }}:
                {{ $result['target_name'] }}
                — {{ $result['is_werewolf'] ? 'เป็นหมาป่า' : 'ไม่ใช่หมาป่า' }}
            </p>
        @empty
            <p>ยังไม่มีผลตรวจ</p>
        @endforelse
    @endif

    @if (
        $game['status'] === 'in_progress'
        && $game['current_phase'] === 'night'
        && $game['phase_end_time'] !== null
    )
        <form
            id="finish-night-form"
            method="POST"
            action="{{ route('games.finish-night', [
                'code' => $game['room_code'],
            ]) }}"
        >
            @csrf

            <input
                type="hidden"
                name="expected_end_time"
                value="{{ $game['phase_end_time'] }}"
            >

            <button type="submit">ตรวจเวลาจบกลางคืน</button>
        </form>
    @endif

    @if (
        $game['current_phase'] === 'night'
        && $game['night_event'] !== null
    )
        <section>
            <h2>
                Event จากคืนรอบ {{ $game['night_event']['night_round'] }}
            </h2>

            <p>{{ $game['night_event']['name'] }}</p>

            @if ($game['night_event']['id'] !== 'none')
                <p>{{ $game['night_event']['description'] }}</p>

                <p>
                    สำหรับกลางวันรอบ
                    {{ $game['night_event']['applies_to_round'] }}
                </p>

                <p>กำลังทดสอบประกาศ Event — ผลจะเริ่มในกลางวันถัดไป โดยโอกาสลงคะแนนใหม่ยังอยู่ระหว่างเชื่อมระบบ</p>
            @endif
        </section>
    @endif


    @if (
        in_array($game['current_phase'], ['day_discussion', 'day_voting'], true)
        && $game['day_event'] !== null
        && $game['day_event']['id'] !== 'none'
    )
        <section>
            <h2>Event กลางวันรอบ {{ $game['day_event']['round'] }}</h2>
            <p>{{ $game['day_event']['name'] }}</p>

            @if ($game['day_event']['applied'])
                <p>{{ $game['day_event']['description'] }}</p>
                <p>มีผลในกลางวันรอบนี้</p>
            @else
                <p>Event นี้ยังไม่เปิดใช้ — รอบนี้ใช้กติกาพื้นฐาน</p>
            @endif
        </section>
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
                            ?? document.getElementById('finish-voting-form')
                            ?? document.getElementById('finish-night-form');

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