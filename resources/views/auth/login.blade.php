<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Masuk - Regional Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h1>Masuk</h1>

    @if ($errors->any())
        <p style="color:red">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label>Username <input type="text" name="username" value="{{ old('username') }}" required autofocus></label><br>
        <label>Password <input type="password" name="password" required></label><br>
        <button type="submit">Masuk</button>
    </form>
</body>
</html>
