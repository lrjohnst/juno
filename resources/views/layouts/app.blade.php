<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ @filemtime(public_path('css/theme.css')) ?: '1' }}">
    <style>:root{ --primary:{{ config('ui-theme.primary') }}; --primary-dark:{{ config('ui-theme.primary_dark') }}; --accent:{{ config('ui-theme.accent') }}; --bg:{{ config('ui-theme.bg') }}; }</style>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) ?: '1' }}">
</head>
<body>
    <header class="app-header">
        <h1>{{ config('app.name') }}</h1>
        <nav class="nav-primary">
            <a href="{{ route('home') }}" @class(['nav-item', 'active' => request()->routeIs('home')])>Home</a>
        </nav>
        <div class="user-chip">
            {{ Auth::user()->name }}
            <form method="post" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm">Uitloggen</button>
            </form>
        </div>
    </header>

    <main>
        @foreach (['success','error','info'] as $type)
            @if (session($type))
                <div class="flash flash-{{ $type }}">{{ session($type) }}</div>
            @endif
        @endforeach
        @if ($errors->any())
            <div class="flash flash-error">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
