<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Test create room</title>
</head>
<body>
    <form method="POST" action="/rooms">
        @csrf

        <label>
            Host Name
            <input name="host_name" required maxlength="50">
        </label>

        <button type="submit">Create room</button>
    </form>

    @error('host_name')
        <p>{{ $message }}</p>
    @enderror
</body>
</html>