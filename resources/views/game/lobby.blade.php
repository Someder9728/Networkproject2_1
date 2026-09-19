<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ware Woof - Lobby</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700;900&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #080714;
            color: #ffffff;
            background: radial-gradient(circle at 50% 30%, #1e1b4b 0%, #080714 50%) !important;
            background-attachment: fixed;
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
            background: radial-gradient(ellipse at 30% 100%, rgba(148, 163, 184, 0.4) 0%, rgba(71, 85, 105, 0.15) 50%, transparent 80%);
            filter: blur(20px);
            animation: fogPulseVisible1 8s ease-in-out infinite alternate !important;
        }

        .smoke-layer-2 {
            position: absolute;
            bottom: -20%;
            right: -10%;
            width: 130%;
            height: 90%;
            background: radial-gradient(ellipse at 70% 100%, rgba(226, 232, 240, 0.35) 0%, rgba(148, 163, 184, 0.1) 45%, transparent 75%);
            filter: blur(25px);
            animation: fogPulseVisible2 11s ease-in-out infinite alternate !important;
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
                opacity: 0.7;
                transform: translate3d(0, 0, 0) scale(1.1);
            }

            50% {
                opacity: 0.3;
                transform: translate3d(5%, -20px, 0) scale(0.95);
            }

            100% {
                opacity: 0.8;
                transform: translate3d(10%, -10px, 0) scale(1.18);
            }
        }

        main,
        .container {
            position: relative;
            z-index: 10 !important;
        }


        .card-create {
            background-color: rgba(58, 12, 89, 0.2);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 1rem;
        }

        .btn-create {
            background-color: #9333ea;
            border: none;
            color: #ffffff;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 700;
            text-decoration: none;
            display: block;
            transition: background-color 0.2s ease;
        }

        .btn-create:hover {
            background-color: #a855f7;
            color: #ffffff;
        }

        .card-join {
            background-color: rgba(23, 37, 84, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 1rem;
        }

        .btn-join {
            background-color: #2563eb;
            border: none;
            color: #ffffff;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 700;
            text-decoration: none;
            display: block;
            transition: background-color 0.2s ease;
        }

        .btn-join:hover {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .section-rooms {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
        }
    </style>
</head>

<body class="min-vh-100 d-flex align-items-center justify-content-center px-3 py-5">

    <div class="smoke-wrapper">
        <div class="smoke-layer-1"></div>
        <div class="smoke-layer-2"></div>
    </div>

    <main class="w-100" style="max-width: 1024px;">

        <div class="text-center mb-5">
            <h1 class="display-4 fw-black letter-spacing-widest text-uppercase fw-bold ">
                WARE <span style="color: #a855f7;">WOOF</span>
            </h1>

            <p class="mt-3 text-secondary">
                Werewolf Mafia Style Multiplayer Game
            </p>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-12 col-md-6">
                <div class="card-create p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="h5 mb-2">
                            สร้างห้อง
                        </h2>

                        <p class="text-secondary mb-4">
                            สร้างห้องใหม่และชวนเพื่อนเข้ามาเล่น
                        </p>
                    </div>

                    <a href="{{ route('game.create-room') }}" class="btn-create text-center">
                        Create Room
                    </a>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card-join p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="h5 mb-2">
                            เข้าร่วมห้อง
                        </h2>

                        <p class="text-secondary mb-4">
                            เลือกห้องที่ต้องการเข้าร่วม
                        </p>
                    </div>

                    <a href="{{ route('game.join-room') }}" class="btn-join text-center">
                        Join Room
                    </a>
                </div>
            </div>

        </div>

        <section class="section-rooms p-4">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <h2 class="h5 mb-0">
                    ห้องที่กำลังเปิดอยู่
                </h2>

                <span class="small text-secondary">
                    0 Rooms
                </span>
            </div>

            <div class="text-center py-5 text-secondary">
                ยังไม่มีห้องที่เปิดอยู่
            </div>

        </section>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
