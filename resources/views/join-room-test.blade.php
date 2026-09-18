<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Test Join room</title>
</head>
<body>
    <h1>Join {{ $code }}</h1>

    <form method="POST" action="{{ url('/rooms/' . $code . '/join') }}">
        @csrf

        <label>
            Palyer name
            <input name="player_name" required maxlength="50">
        </label>

        <button type="submit">Join Room</button>
    </form>

    @foreach ($errors->all() as $error)
        <p>{{ $error }}</p>
    @endforeach
</body>
</html>