<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dashboard - Regional Dashboard</title>
</head>
<body>
    <p>Login berhasil sebagai {{ auth()->user()->name }} ({{ $effectiveRole }}@if($effectiveRole !== $actualRole) &mdash; role asli: {{ $actualRole }}@endif).</p>
    <p>Halaman dashboard sungguhan menyusul di Fase 3 Batch A.</p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</body>
</html>
