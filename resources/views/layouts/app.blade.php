<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    <header class="border-b border-stone-300 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ route('sources.index') }}" class="text-lg font-semibold tracking-tight">
                {{ config('app.name') }}
            </a>
            <nav class="flex gap-4 text-sm">
                <a href="{{ route('sources.index') }}" class="text-stone-700 hover:text-stone-950">Quellen</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <p class="mb-4 border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </p>
        @endif

        @yield('content')
    </main>
</body>
</html>
