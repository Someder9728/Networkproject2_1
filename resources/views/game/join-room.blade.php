<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ware Woof - Join Room</title>

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
            background-color: rgba(23, 37, 84, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 1rem;
        }

        .form-control-custom {
            background-color: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease-in-out;
        }

        .form-control-custom::placeholder {
            color: #6c757d;
        }

        .form-control-custom:hover {
            border-color: #2563eb !important;
        }

        .form-control-custom:focus {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.3) !important;
        }

        .btn-back {
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

        .btn-back:hover {
            background-color: rgba(255, 255, 255, 0.1);
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
    </style>
</head>

<body class="min-vh-100 d-flex align-items-center justify-content-center px-3 py-5">

    <div class="smoke-wrapper">
        <div class="smoke-layer-1"></div>
        <div class="smoke-layer-2"></div>
    </div>

    <main class="w-100" style="max-width: 672px;">

        <div class="text-center mb-5">

            <h1 class="display-5 fw-black letter-spacing-widest text-uppercase fw-bold">
                JOIN <span style="color: #a855f7;">ROOM</span>
            </h1>

            <p class="mt-3 text-secondary">
                เข้าร่วมห้องเกม Ware Woof
            </p>

        </div>

        <div class="custom-card p-4 p-md-5">

            <div class="mb-5">

                <label for="roomCode" class="form-label">
                    รหัสห้อง
                </label>

                <input id="roomCode" type="text" placeholder="กรอกรหัสห้อง"
                    class="form-control form-control-custom">

            </div>

            <div class="row g-3">

                <div class="col-6">
                    <a href="/lobby" class="btn btn-back w-100 text-center">
                        Back
                    </a>
                </div>

                <div class="col-6">
                    <button id="joinRoomBtn" type="button" class="btn btn-join w-100">
                        Join Room
                    </button>
                </div>

            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
