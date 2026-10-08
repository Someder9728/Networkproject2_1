<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ware Woof - Create or Join Room</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700;900&display=swap" rel="stylesheet">

    <style>
    @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&display=swap');

    body {
        min-height: 100vh;
        font-family: 'Kanit', sans-serif;
        color: #ffffff;
        background:
            radial-gradient(circle at 50% 30%,
                #1e1b4b 0%,
                #080714 50%);
        background-color: #080714;
        background-attachment: fixed;
    }

    h1 {
        font-family: 'Cinzel', serif;
    }

    body::after {
        content: '';
        position: fixed;
        right: 6%;
        top: 45px;
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #ffd66b;
        box-shadow:
            0 0 20px rgba(255, 214, 107, .65),
            0 0 60px rgba(255, 166, 0, .35);
        opacity: .95;
        pointer-events: none;
        z-index: 0;
    }

    .smoke-wrapper {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100vw;
        height: 45vh;
        overflow: hidden;
        pointer-events: none;
        z-index: 1;
    }

    .smoke-layer-1 {
        position: absolute;
        bottom: -15%;
        left: -10%;
        width: 120%;
        height: 100%;
        background:
            radial-gradient(ellipse at 30% 100%,
                rgba(148, 163, 184, 0.4) 0%,
                rgba(71, 85, 105, 0.15) 50%,
                transparent 80%);
        filter: blur(20px);
        animation: fogPulseVisible1 8s ease-in-out infinite alternate;
    }

    .smoke-layer-2 {
        position: absolute;
        bottom: -20%;
        right: -10%;
        width: 130%;
        height: 90%;
        background:
            radial-gradient(ellipse at 70% 100%,
                rgba(226, 232, 240, 0.35) 0%,
                rgba(148, 163, 184, 0.1) 45%,
                transparent 75%);
        filter: blur(25px);
        animation: fogPulseVisible2 11s ease-in-out infinite alternate;
    }

    @keyframes fogPulseVisible1 {
        0% {
            opacity: 0.3;
            transform: translate3d(0, 0, 0) scale(0.9);
        }

        50% {
            opacity: 0.8;
            transform: translate3d(-4%, -15px, 0) scale(1.15);
        }

        100% {
            opacity: 0.4;
            transform: translate3d(-8%, -5px, 0) scale(1.02);
        }
    }

    @keyframes fogPulseVisible2 {
        0% {
            opacity: 0.4;
            transform: translate3d(0, 0, 0) scale(1.1);
        }

        50% {
            opacity: 0.3;
            transform: translate3d(5%, -20px, 0) scale(0.95);
        }

        100% {
            opacity: 0.6;
            transform: translate3d(10%, -10px, 0) scale(1.18);
        }
    }


    @keyframes floatSmoke {

        0%,
        100% {
            transform: translate(0, 0) scale(1);
        }

        50% {
            transform: translate(40px, -30px) scale(1.15);
        }
    }

    main,
    .container {
        position: relative;
        z-index: 10;
    }

    .page-title {
        font-weight: 900;
        letter-spacing: 0.08em;
    }

    .page-title span {
        color: #a855f7;
    }

    .page-subtitle {
        color: #94a3b8;
    }



    .room-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
    }

    .room-section {
        padding: 28px;
        background-color: rgba(8, 7, 20, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 1rem;
        display: flex;
        flex-direction: column;
    }

    .room-section form {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .room-section form>button[type="submit"] {
        margin-top: auto;
        width: 100%;
        min-height: 58px;
    }

    .section-title {
        font-weight: 700;
    }

    .section-description {
        color: #94a3b8;
    }

    .form-label {
        color: #e2e8f0;
        font-weight: 500;
    }

    .form-control-custom,
    .form-select-custom,
    .form-control-customm,
    .form-select-customm {
        background-color: rgba(0, 0, 0, 0.3) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        transition: all 0.2s ease-in-out;
    }

    .form-control-custom::placeholder,
    .form-control-customm::placeholder {
        color: #6c757d;
    }

    .form-control-customm:hover,
    .form-select-customm:hover {
        border-color: #2563eb !important;
    }

    .form-control-customm:focus,
    .form-select-customm:focus {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.3) !important;
    }

    .form-control-custom:hover,
    .form-select-custom:hover {
        border-color: #9333ea !important;
    }

    .form-control-custom:focus,
    .form-select-custom:focus {
        border-color: #9333ea !important;
        box-shadow: 0 0 0 0.25rem rgba(139, 92, 246, 0.3) !important;
    }

    .form-select-custom option {
        background-color: #111126;
        color: #ffffff;
    }

    .btn-create {
        background-color: #9333ea;
        border: none;
        color: #ffffff;
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        font-weight: 700;
        transition: background-color 0.2s ease;
    }

    .btn-create:hover {
        background-color: #a855f7;
        color: #ffffff;
    }

    .btn-join {
        background-color: #2563eb;
        border: none;
        color: #ffffff;
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        font-weight: 700;
        transition: background-color 0.2s ease;
    }

    .btn-join:hover {
        background-color: #3b82f6;
        color: #ffffff;
    }

    .current-room {
        background-color: rgba(168, 85, 247, 0.08);
        border: 1px solid rgba(168, 85, 247, 0.25);
        border-radius: 0.75rem;
    }

    .current-room-code {
        color: #c084fc;
        font-weight: 700;
        letter-spacing: 0.08em;
    }

    .current-room-link {
        color: #c084fc;
        text-decoration: none;
        font-weight: 600;
    }

    .current-room-link:hover {
        color: #d8b4fe;
    }

    .btn-cleanup {
        background-color: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
        border-radius: 0.75rem;
        padding: 0.65rem 1rem;
        font-weight: 600;
    }

    .btn-cleanup:hover {
        background-color: rgba(239, 68, 68, 0.2);
        color: #fecaca;
    }

    .alert-custom {
        background-color: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fecaca;
        border-radius: 0.75rem;
    }

    .required-mark {
        color: #c084fc;
    }

    .required-markk {
        color: #2563eb;
    }

    /* leave room modal */

    .leave-room-button {
        width: 100%;
        padding: 14px 20px;
        border: 1px solid rgba(239, 68, 68, 0.35);
        border-radius: 14px;
        background: rgba(127, 29, 29, 0.2);
        color: #fca5a5;
        font-family: 'Kanit', sans-serif;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .leave-room-button:hover {
        border-color: rgba(239, 68, 68, 0.65);
        background: rgba(127, 29, 29, 0.35);
        color: #fecaca;
    }

    .leave-room-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;

        display: none;
        align-items: center;
        justify-content: center;

        padding: 20px;

        background: rgba(3, 3, 15, 0.78);
        backdrop-filter: blur(8px);
    }

    .leave-room-modal.show {
        display: flex;
    }

    .leave-room-modal-card {
        width: min(430px, 100%);

        padding: 30px;

        border: 1px solid rgba(168, 85, 247, 0.25);
        border-radius: 22px;

        background:
            linear-gradient(145deg,
                rgba(30, 27, 75, 0.98),
                rgba(8, 7, 20, 0.98));

        box-shadow:
            0 25px 80px rgba(0, 0, 0, 0.55),
            0 0 40px rgba(124, 58, 237, 0.12);

        text-align: center;
    }

    .leave-room-modal-icon {
        width: 58px;
        height: 58px;

        margin: 0 auto 18px;

        display: flex;
        align-items: center;
        justify-content: center;

        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 50%;

        background: rgba(127, 29, 29, 0.2);

        color: #fca5a5;
        font-size: 26px;
    }

    .leave-room-modal-title {
        margin: 0 0 10px;

        color: #f8fafc;

        font-family: 'Kanit', sans-serif;
        font-size: 22px;
        font-weight: 700;
    }

    .leave-room-modal-text {
        margin: 0 auto 25px;

        color: #94a3b8;

        font-family: 'Kanit', sans-serif;
        font-size: 14px;
        line-height: 1.7;
    }

    .leave-room-modal-actions {
        display: flex;
        gap: 10px;
    }

    .leave-room-modal-cancel,
    .leave-room-modal-confirm {
        flex: 1;

        padding: 11px 16px;

        border-radius: 12px;

        font-family: 'Kanit', sans-serif;
        font-size: 14px;
        font-weight: 700;

        cursor: pointer;
        transition: 0.2s ease;
    }

    .leave-room-modal-cancel {
        border: 1px solid rgba(100, 116, 139, 0.3);
        background: rgba(15, 23, 42, 0.7);
        color: #94a3b8;
    }

    .leave-room-modal-cancel:hover {
        border-color: rgba(148, 163, 184, 0.5);
        background: rgba(30, 41, 59, 0.8);
        color: #e2e8f0;
    }

    .leave-room-modal-confirm {
        border: 1px solid rgba(239, 68, 68, 0.4);
        background: rgba(127, 29, 29, 0.7);
        color: #fecaca;
    }

    .leave-room-modal-confirm:hover {
        border-color: rgba(239, 68, 68, 0.7);
        background: rgba(153, 27, 27, 0.85);
        color: #ffffff;
    }

    .difficulty-card {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        width: 100%;
        min-height: 30px;
        padding: 15px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 14px;
        background: rgba(15, 23, 42, 0.75);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .difficulty-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 10px;
    }

    .difficulty-card:hover {
        border-color: #a855f7;
        background: rgba(30, 27, 75, 0.9);
        transform: translateY(-2px);
    }

    .difficulty-card input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .difficulty-card:has(input:checked) {
        border-color: #a855f7;
        background: rgba(88, 28, 135, 0.3);
        box-shadow: 0 0 0 1px rgba(168, 85, 247, 0.25);
    }


    .difficulty-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .difficulty-title {
        color: #f8fafc;
        font-size: 16px;
        font-weight: 700;
    }

    .difficulty-description {
        color: #94a3b8;
        font-size: 13px;
    }

    .difficulty-check {
        position: absolute;
        top: 14px;
        right: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 50%;
        color: transparent;
        font-size: 13px;
    }

    .difficulty-card:has(input:checked) .difficulty-check {
        border-color: #a855f7;
        background: #a855f7;
        color: #fff;
    }

    @media (max-width: 768px) {
        .room-grid {
            grid-template-columns: 1fr;
        }

        .room-section {
            padding: 24px;
        }
    }

    @media (max-width: 600px) {
        .difficulty-list {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body class="d-flex align-items-center justify-content-center px-3 py-5">

    <div class="smoke-wrapper">
        <div class="smoke-layer-1"></div>
        <div class="smoke-layer-2"></div>
    </div>

    <main class="w-100" style="max-width: 1100px;">

        <div class="text-center mb-5">

            <h1 class="display-5 page-title text-uppercase">
                WARE <span>WOLF</span>
            </h1>

            <p class="page-subtitle mt-3 mb-0">
                สร้างหรือเข้าร่วมห้องเกม Werewolf
            </p>

        </div>

        @if ($currentRoom !== null)
        <div class="current-room p-4 mb-4">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

                <div>
                    <div class="small text-secondary">
                        ห้องล่าสุดของคุณ
                    </div>

                    <div class="current-room-code fs-5 mt-1">
                        {{ $currentRoom['code'] }}
                    </div>
                </div>

                <a href="{{ route('rooms.show', ['code' => $currentRoom['code']]) }}" class="current-room-link">
                    {{ $currentRoom['game_uuid'] !== null
                            ? 'กลับเข้าเกม →'
                            : 'กลับ Lobby →' }}
                </a>

            </div>

        </div>
        @endif

        @if ($errors->any())
        <div class="alert alert-custom mb-4" role="alert">

            <div class="fw-bold mb-2">
                ไม่สามารถดำเนินการได้
            </div>

            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>

        </div>
        @endif

        <div class="custom-card">

            <div class="room-grid">

                {{-- create room --}}
                <section class="room-section">

                    <div class="mb-4">
                        <h2 class="section-title h4 mb-1">
                            Create Room
                        </h2>

                        <p class="section-description mb-0">
                            สร้างห้องใหม่แล้วรอผู้เล่นเข้าร่วม
                        </p>
                    </div>

                    <form method="POST" action="{{ route('rooms.store') }}">
                        @csrf

                        <div class="mb-4">

                            <label for="host_name" class="form-label">
                                ชื่อของคุณ
                                <span class="required-mark">*</span>
                            </label>

                            <input id="host_name" type="text" name="host_name" value="{{ old('host_name') }}"
                                maxlength="45" required autofocus autocomplete="name" placeholder="กรอกชื่อของคุณ"
                                class="form-control form-control-custom">

                        </div>

                        <div class="mb-4">

                            <div class="form-label">
                                ระดับความยาก
                                <span class="required-mark">*</span>
                            </div>

                            <div class="difficulty-list">

                                <label class="difficulty-card">
                                    <input type="radio" name="difficulty" value="easy" checked>

                                    <span class="difficulty-info">
                                        <span class="difficulty-title">
                                            Easy
                                        </span>

                                        <span class="difficulty-description">
                                            เปิดเผย Role ของคนตาย
                                        </span>
                                    </span>

                                    <span class="difficulty-check">
                                        ✓
                                    </span>
                                </label>


                                <label class="difficulty-card">
                                    <input type="radio" name="difficulty" value="hard">

                                    <span class="difficulty-info">
                                        <span class="difficulty-title">
                                            Hard
                                        </span>

                                        <span class="difficulty-description">
                                            ไม่เปิดเผย Role ของคนตาย
                                        </span>
                                    </span>

                                    <span class="difficulty-check">
                                        ✓
                                    </span>
                                </label>

                            </div>

                        </div>

                        <div class="mb-4">
                            <label for="player-limit" class="form-label">จำนวนผู้เล่น</label>
                            <select id="player-limit" name="player_limit" class="form-control form-control-custom" required>
                                @foreach (\App\GameLogic\DifficultyConfig::supportedPlayerCounts() as $limit)
                                <option value="{{ $limit }}" @selected((int) old('player_limit', 4) === $limit)>{{ $limit }} คน</option>
                                @endforeach
                            </select>
                            <p class="section-description mt-2">เมื่อคนครบและทุกคนกดพร้อม จะนับถอยหลัง {{ max(1, (int) config('game.lobby_countdown_seconds', 5)) }} วินาทีแล้วเริ่มอัตโนมัติ</p>
                        </div>
                        <div class="mb-4">
                            <label for="game-mode" class="form-label">โหมดเกม</label>
                            <select name="game_mode" id="game-mode" class="form-control form-control-custom">
                                <option value="normal" @selected(old('game_mode', 'normal') === 'normal')>ปกติ</option>
                                <option value="short" @selected(old('game_mode') === 'short')>เกมสั้น</option>
                            </select>
                            <p class="section-description">เกมสั้น: สูงสุด {{ config('game.short_max_rounds', 3) }} รอบ หรือ {{ config('game.short_duration_seconds', 480) / 60 }} นาที ชาวบ้านชนะถ้าหมาป่ายังชนะไม่ได้เมื่อครบกำหนด</p>
                            <p class="section-description">ห้อง 5–10 คนมีผู้คุ้มกัน 1 คน ป้องกันคนเดิมติดกันไม่ได้</p>
                        </div>
                        <button type="submit" class="btn btn-create w-100">
                            Create Room
                        </button>

                    </form>

                </section>

                {{-- join room --}}
                <section class="room-section">

                    <div class="mb-4">
                        <h2 class="section-title h4 mb-1">
                            Join Room
                        </h2>

                        <p class="section-description mb-0">
                            เข้าร่วมห้องเกมด้วยรหัสห้อง
                        </p>
                    </div>

                    <form method="POST" action="{{ route('rooms.join-form') }}">
                        @csrf

                        <div class="mb-4">

                            <label for="player_name" class="form-label">
                                ชื่อของคุณ
                                <span class="required-markk">*</span>
                            </label>

                            <input id="player_name" type="text" name="player_name" value="{{ old('player_name') }}"
                                maxlength="45" required autocomplete="name" placeholder="กรอกชื่อของคุณ"
                                class="form-control form-control-customm">

                        </div>

                        <div class="mb-4">

                            <label for="code" class="form-label">
                                รหัสห้อง
                                <span class="required-markk">*</span>
                            </label>

                            <input id="code" type="text" name="code" value="{{ old('code') }}" minlength="6"
                                maxlength="6" pattern="[A-Za-z0-9]{6}" required autocomplete="off" placeholder="ABC123"
                                class="form-control form-control-customm text-uppercase">

                        </div>

                        <button type="submit" class="btn btn-join w-100">
                            Join Room
                        </button>

                    </form>

                </section>

            </div>

        </div>

        @if ($currentRoom !== null)
        <div class="text-center mt-4">

            <form id="leave-room-form" method="POST"
                action="{{ route('games.leave-unavailable', ['code' => $currentRoom['code']]) }}">
                @csrf

                <button type="button" id="open-leave-room-modal" class="leave-room-button">
                    ออกจากห้องที่ค้าง
                </button>
            </form>

        </div>
        @endif

    </main>

    <div id="leave-room-modal" class="leave-room-modal" aria-hidden="true">
        <div class="leave-room-modal-card" role="dialog" aria-modal="true" aria-labelledby="leave-room-modal-title">

            <div class="leave-room-modal-icon">
                ↪
            </div>

            <h2 id="leave-room-modal-title" class="leave-room-modal-title">
                ออกจากห้องที่ค้าง?
            </h2>

            <p class="leave-room-modal-text">
                การออกครั้งนี้จะเป็นการออกจากห้องอย่างถาวร
            </p>

            <div class="leave-room-modal-actions">

                <button type="button" id="cancel-leave-room" class="leave-room-modal-cancel">
                    ยกเลิก
                </button>

                <button type="button" id="confirm-leave-room" class="leave-room-modal-confirm">
                    ออกจากห้อง
                </button>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("leave-room-form");
    const openButton = document.getElementById("open-leave-room-modal");
    const modal = document.getElementById("leave-room-modal");
    const cancelButton = document.getElementById("cancel-leave-room");
    const confirmButton = document.getElementById("confirm-leave-room");

    if (!form || !openButton || !modal || !cancelButton || !confirmButton) {
        return;
    }

    function openModal() {
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }

    function closeModal() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
    }

    openButton.addEventListener("click", () => {
        openModal();
    });

    cancelButton.addEventListener("click", () => {
        closeModal();
    });

    confirmButton.addEventListener("click", () => {
        form.submit();
    });

    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeModal();
        }
    });
});
</script>

</html>
