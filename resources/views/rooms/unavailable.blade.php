<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ไม่สามารถเปิดเกมได้</title>

    <style>
        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            box-sizing: border-box;
            display: grid;
            place-items: center;
            background: #111827;
            color: #f9fafb;
            font-family: sans-serif;
        }

        main {
            width: 100%;
            max-width: 460px;
            padding: 28px;
            box-sizing: border-box;
            background: #1f2937;
            border-radius: 16px;
        }

        p {
            line-height: 1.7;
            color: #d1d5db;
        }

        button, a {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font: inherit;
        }

        button {
            border: 0;
            background: #fbbf24;
            color: #111827;
            cursor: pointer;
        }

        a {
            margin-top: 12px;
            color: #e5e7eb;
        }
    </style>
</head>
<body>
    <main>
        <h1>ไม่สามารถเปิดเกมนี้ได้</h1>

        <p>ห้อง {{ $code }} มีปัญหาข้อมูลเกม จึงยังเล่นต่อไม่ได้</p>

        <p>
            คุณสามารถออกจากห้องนี้ แล้วสร้างหรือเข้าร่วมห้องใหม่ได้
            เมื่อออกแล้วจะกลับเข้าห้องเดิมไม่ได้
        </p>

        <form
            method="POST"
            action="{{ route('games.leave-unavailable', ['code' => $code]) }}"
            onsubmit="return confirm('ยืนยันออกจากห้องนี้ถาวรหรือไม่?')"
        >
            @csrf

            <button type="submit">
                ออกจากห้องนี้
            </button>
        </form>

        <a href="{{ route('rooms.index') }}">
            กลับหน้าหลักโดยยังไม่ออกจากห้อง
        </a>
    </main>
</body>
</html>