<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ware Woof - Player List</title>

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

        .custom-card {
            background-color: rgba(58, 12, 89, 0.2);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 1rem;
        }
        
        .player-item {
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .avatar-host {
            background-color: #9333ea;
        }

        .avatar-player {
            background-color: #2563eb;
        }

        .btn-leave {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s ease;
        }

        .btn-leave:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .btn-start {
            background-color: #9333ea;
            border: none;
            color: #ffffff;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 700;
            transition: background-color 0.2s ease;
        }

        .btn-start:hover {
            background-color: #a855f7;
            color: #ffffff;
        }
    </style>
</head>

<body class="min-vh-100 d-flex align-items-center justify-content-center px-3 py-5">

    <div class="smoke-wrapper">
        <div class="smoke-layer-1"></div>
        <div class="smoke-layer-2"></div>
    </div>

    <main class="w-100" style="max-width: 768px;">

        <div class="text-center mb-5">
 
            <h1 class="display-5 fw-black letter-spacing-widest text-uppercase fw-bold">
                PLAYER <span style="color: #a855f7;">LIST</span>
            </h1>

            <p class="mt-3 text-secondary">
                รายชื่อผู้เล่นในห้อง
            </p>

        </div>

        <div class="custom-card p-4 mb-4">

            <div class="d-flex align-items-center justify-content-between">

                <div>
                    <p class="small text-secondary mb-0">
                        ห้อง
                    </p>

                    <h2 class="h5 mt-1 mb-0">
                        Ware Woof Room
                    </h2>
                </div>

                <div class="text-end">

                    <p class="small text-secondary mb-0">
                        ผู้เล่น
                    </p>

                    <p class="h5 mt-1 mb-0">
                        <span style="color: #c084fc;">3</span> / 6
                    </p>

                </div>

            </div>

        </div>

        <div class="custom-card p-4">

            <h2 class="h5 mb-4">
                รายชื่อผู้เล่น
            </h2>


            <div class="d-flex flex-column gap-3">

                <div class="player-item d-flex align-items-center justify-content-between">

                    <div class="d-flex align-items-center gap-3">

                        <div class="avatar avatar-host">
                            P
                        </div>

                        <div>
                            <p class="mb-0">
                                Player 1
                            </p>

                            <p class="small mb-0" style="color: #c084fc;">
                                Host
                            </p>
                        </div>

                    </div>

                    <span class="small text-success">
                        Ready
                    </span>

                </div>


                <div class="player-item d-flex align-items-center justify-content-between">

                    <div class="d-flex align-items-center gap-3">

                        <div class="avatar avatar-player">
                            P
                        </div>

                        <div>
                            <p class="mb-0">
                                Player 2
                            </p>

                            <p class="small text-secondary mb-0">
                                Player
                            </p>
                        </div>

                    </div>

                    <span class="small text-success">
                        Ready
                    </span>

                </div>


                <div class="player-item d-flex align-items-center justify-content-between">

                    <div class="d-flex align-items-center gap-3">

                        <div class="avatar avatar-player">
                            P
                        </div>

                        <div>
                            <p class="mb-0">
                                Player 3
                            </p>

                            <p class="small text-secondary mb-0">
                                Player
                            </p>
                        </div>

                    </div>

                    <span class="small text-success">
                        Ready
                    </span>

                </div>

            </div>

            <div class="row g-3 mt-4">

                <div class="col-6">
                    <a href="/lobby" class="btn btn-leave w-100 text-center">
                        Leave Room
                    </a>
                </div>

                <div class="col-6">
                    <button type="button" class="btn btn-start w-100">
                        Start Game
                    </button>
                </div>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
