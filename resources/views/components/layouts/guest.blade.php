<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Welcome' }} · {{ \App\Models\Setting::get('school_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white font-sans text-slate-800 antialiased">
    <header class="border-b border-slate-200">
        <div class="mx-auto flex h-16 max-w-5xl items-center gap-3 px-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                @if(\App\Models\Setting::get('logo_path'))
                    <img src="{{ route('school.logo') }}" class="h-9 w-9 rounded-lg object-contain" alt="">
                @else
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white"><x-icon name="cap" class="h-5 w-5" /></div>
                @endif
                <span class="text-sm font-semibold text-slate-900">{{ \App\Models\Setting::get('school_name') }}</span>
            </a>
            <nav class="ml-auto flex items-center gap-1 text-sm">
                <a href="{{ route('apply.create') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('apply.create') ? 'font-semibold text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">Apply</a>
                <a href="{{ route('apply.status') }}" class="hidden rounded-lg px-3 py-2 sm:block {{ request()->routeIs('apply.status') ? 'font-semibold text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">Check status</a>
                <a href="{{ route('login') }}" class="ml-2 rounded-lg border border-slate-300 px-3.5 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Log in</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
        @if(session('ok'))<div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">{{ session('ok') }}</div>@endif
        {{ $slot }}
    </main>
    <footer class="border-t border-slate-200">
        <div class="mx-auto flex max-w-5xl flex-wrap justify-between gap-2 px-4 py-6 text-xs text-slate-500 sm:px-6">
            <span>© {{ date('Y') }} {{ \App\Models\Setting::get('school_name') }} · Powered by SmartSchool</span>
            <a href="{{ route('privacy') }}" class="hover:text-slate-900">Privacy notice</a>
        </div>
    </footer>
</body>
</html>
