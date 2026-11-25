@php
    $u = auth()->user();
    $nav = \App\Support\Nav::for($u);
    $primary = \App\Support\Nav::primary($u);
    $palette = collect($nav)->flatMap(fn ($items, $group) => collect($items)->map(fn ($i) => ['label' => $i['label'], 'hint' => $i['desc'], 'url' => $i['url'], 'group' => $group ?: 'Go to']))->values();
    $unread = $u->unreadNotifications()->count();
    $school = \App\Models\Setting::get('school_name');
    $logo = \App\Models\Setting::logoUrl();
    $initials = collect(explode(' ', $u->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $isPortal = $u->hasRole('student', 'parent');
    $canSearch = $u->hasRole('admin', 'registrar', 'teacher', 'cashier', 'nurse', 'guidance', 'librarian');
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950" x-data :class="{ dark: $store.theme.dark }">
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">if (localStorage.theme === 'dark') document.documentElement.classList.add('dark')</script>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title>{{ $title ?? 'Home' }} · {{ $school }}</title>
    @include('components.layouts._favicon', ['logo' => $logo])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased dark:text-slate-200" x-data="{ menu: false, mini: localStorage.getItem('navMini') === '1' }" x-init="$watch('mini', v => localStorage.setItem('navMini', v ? '1' : '0'))" @keydown.window.prevent.ctrl.k="$dispatch('palette')" @keydown.window.prevent.meta.k="$dispatch('palette')">

{{-- Mobile drawer backdrop --}}
<div x-show="menu" x-cloak x-transition.opacity @click="menu=false" class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

{{-- Sidebar --}}
<aside :class="[menu ? 'translate-x-0' : '-translate-x-full', mini ? 'lg:w-20' : 'lg:w-72']" class="fixed inset-y-0 left-0 z-50 flex w-72 transform flex-col bg-white ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10 transition-[transform,width] duration-300 lg:translate-x-0">
    <a href="{{ $isPortal ? route('portal') : route('dashboard') }}" class="flex items-center gap-3 px-6 pt-6 pb-4">
        @if($logo)
            <img src="{{ $logo }}" class="h-10 w-10 rounded-xl object-contain ring-1 ring-slate-200" alt="">
        @else
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white"><x-icon name="cap" class="h-5 w-5" /></div>
        @endif
        <div class="min-w-0" :class="mini && 'lg:hidden'">
            <div class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $school }}</div>
            <div class="text-xs text-slate-500">{{ $isPortal ? ($u->role === 'parent' ? 'Parent portal' : 'Student portal') : 'Staff portal' }}</div>
        </div>
        <button @click.prevent="menu=false" class="ml-auto rounded-lg p-1 text-slate-400 hover:bg-slate-100 lg:hidden"><x-icon name="x" /></button>
    </a>

    <nav class="flex-1 overflow-y-auto px-4 py-4">
        <div class="space-y-1">
            @foreach($nav as $group => $items)
                @php $hasActive = collect($items)->contains('active', true); @endphp
                @if($group === '')
                    @foreach($items as $i)@include('components.layouts._nav-link', ['i' => $i])@endforeach
                @else
                    <div x-data="{ open: {{ $hasActive ? 'true' : 'false' }} || localStorage.getItem('nav:{{ $group }}') === '1' }" class="pt-2">
                        <button type="button" :class="mini && 'lg:hidden'" class="flex min-h-9 w-full items-center gap-2 rounded-lg px-3 py-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase hover:text-slate-600" :aria-expanded="open" x-on:click="open = !open; localStorage.setItem('nav:{{ $group }}', open ? '1' : '0')">
                            {{ $group }}
                            <x-icon name="chevron" class="ml-auto h-3.5 w-3.5 transition" ::class="open ? 'rotate-90' : ''" />
                        </button>
                        <div x-show="open || mini" x-cloak class="mt-0.5 space-y-0.5">
                            @foreach($items as $i)@include('components.layouts._nav-link', ['i' => $i])@endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </nav>
    <button type="button" class="hidden min-h-11 items-center gap-3 border-t border-slate-100 px-7 py-3 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-800 lg:flex dark:border-white/10 dark:hover:bg-white/5" x-on:click="mini = !mini" :aria-label="mini ? 'Expand menu' : 'Collapse menu'">
        <x-icon name="chevron-left" class="h-5 w-5 shrink-0 transition" ::class="mini && 'rotate-180'" /><span :class="mini && 'lg:hidden'">Collapse menu</span>
    </button>

</aside>

<div :class="mini ? 'lg:pl-20' : 'lg:pl-72'" class="lg:pl-72 transition-[padding] duration-300">
    {{-- Top bar --}}
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur dark:border-white/10 dark:bg-slate-900/80">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
            <button class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="menu=true" aria-label="Open menu"><x-icon name="menu" /></button>
            <button @click="$dispatch('palette')" class="flex max-w-md flex-1 items-center gap-3 rounded-xl bg-slate-100 px-3.5 py-2.5 text-left text-sm text-slate-400 hover:bg-slate-200/70 dark:bg-white/5 dark:hover:bg-white/10">
                <x-icon name="search" class="h-4 w-4" /><span class="flex-1 truncate">{{ $canSearch ? 'Search students or jump to a page…' : 'Search…' }}</span>
                <kbd class="hidden rounded-md bg-white px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 shadow-sm sm:inline dark:bg-white/10">Ctrl K</kbd>
            </button>
            <div class="ml-auto flex items-center gap-1">
                <a href="{{ route('guide') }}" class="hidden items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:flex dark:text-slate-300 dark:hover:bg-white/10"><x-icon name="sparkles" class="h-4 w-4 text-violet-500" /> How it works</a>
                <a href="{{ route('notifications') }}" class="relative rounded-xl p-2.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="Notifications">
                    <x-icon name="bell" />
                    @if($unread)<span class="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
                </a>
                <div x-data="{ o:false }" class="relative">
                    <button @click="o=!o" class="flex items-center gap-2 rounded-xl p-1.5 pr-2.5 hover:bg-slate-100 dark:hover:bg-white/10" aria-label="Account menu" :aria-expanded="o">
                        @if($u->avatar_path)<img src="{{ route('profile.photo', $u) }}" class="h-8 w-8 rounded-full object-cover" alt="">@else<span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ $initials }}</span>@endif
                        <span class="hidden text-left sm:block"><span class="block max-w-40 truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $u->name }}</span><span class="block text-xs text-slate-500">{{ $u->roleLabel() }}</span></span>
                    </button>
                    <div x-show="o" x-cloak x-transition @click.outside="o=false" class="absolute right-0 mt-2 max-h-[80vh] w-72 overflow-y-auto rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10">
                        <div class="border-b border-slate-100 px-4 py-3"><div class="text-sm font-semibold">{{ $u->name }}</div><div class="truncate text-xs text-slate-500">{{ $u->email }}</div></div>
                        <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center gap-2 px-4 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-white/5"><x-icon name="user" class="h-4 w-4 text-slate-400" /> My profile</a>
                        <a href="{{ route('privacy') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-slate-50"><x-icon name="shield" class="h-4 w-4 text-slate-400" /> Privacy</a>
                        <button type="button" @click="$store.theme.toggle()" class="flex min-h-11 w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5">
                            <svg x-show="!$store.theme.dark" class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                            <svg x-show="$store.theme.dark" x-cloak class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                            <span x-text="$store.theme.dark ? 'Light mode' : 'Dark mode'">Dark mode</span>
                        </button>
                        @if(\App\Http\Controllers\DevController::enabled())
                            @php $devLan = \App\Support\School::isLan(); @endphp
                            <div class="border-t border-slate-100 dark:border-white/10" x-data="{ dev: false }">
                                <button type="button" @click="dev = !dev" class="flex min-h-11 w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-fuchsia-700 hover:bg-fuchsia-50 dark:text-fuchsia-300 dark:hover:bg-fuchsia-500/10">
                                    <x-icon name="key" class="h-4 w-4" /> Developer mode
                                    @if(session('dev_unlocked'))<span class="ml-auto rounded-full bg-fuchsia-100 px-2 text-[11px] font-bold dark:bg-fuchsia-500/20">{{ $devLan ? 'LAN' : 'Online' }}</span>@else<span class="ml-auto text-xs text-slate-400">locked</span>@endif
                                </button>
                                <div x-show="dev" x-cloak>
                                    @if(session('dev_unlocked'))
<div class="border-b border-slate-100 px-4 py-3 dark:border-white/10">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Mode</p>
                                <form method="post" action="{{ route('dev.mode') }}" class="mt-2 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5">@csrf
                                    <button name="mode" value="lan" class="rounded-lg px-2 py-1.5 text-sm font-semibold {{ $devLan ? 'bg-white shadow text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-500' }}">Offline (LAN)</button>
                                    <button name="mode" value="hosted" class="rounded-lg px-2 py-1.5 text-sm font-semibold {{ ! $devLan ? 'bg-white shadow text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-500' }}">Online</button>
                                </form>
                            </div>
                            <form method="post" action="{{ route('dev.setup') }}" class="border-b border-slate-100 px-4 py-3 dark:border-white/10">@csrf
                                <button class="flex w-full items-center justify-center gap-2 rounded-xl bg-fuchsia-600 px-3 py-2 text-sm font-semibold text-white hover:bg-fuchsia-700"><x-icon name="sparkles" class="h-4 w-4" /> Run setup wizard again</button>
                                <p class="mt-1 text-center text-[11px] text-slate-500">Signs in as the Superadmin if needed</p>
                            </form>
                            <div class="max-h-80 overflow-y-auto px-2 py-2">
                                <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Sign in as</p>
                                @foreach(\App\Http\Controllers\DevController::accounts() as $acc)
                                    <form method="post" action="{{ route('dev.login') }}">@csrf<input type="hidden" name="user_id" value="{{ $acc->id }}">
                                        <button class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-white/10 {{ $acc->id === $u->id ? 'font-semibold text-brand-700' : '' }}">
                                            <span class="w-20 shrink-0 text-xs capitalize {{ $acc->role === 'admin' ? 'font-bold text-fuchsia-600' : 'text-slate-500' }}">{{ $acc->role === 'admin' ? 'Superadmin' : $acc->role }}</span><span class="truncate">{{ $acc->name }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                                    @else
                                        <form method="post" action="{{ route('dev.unlock') }}" class="space-y-2 px-4 pb-3">@csrf
                                            <input type="password" name="password" class="input h-10" placeholder="Developer password" autocomplete="off" required>
                                            <button class="btn btn-sm w-full">Unlock</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <form method="post" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50"><x-icon name="logout" class="h-4 w-4" /> Log out</button></form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Toast messages --}}
    <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex flex-col items-center gap-2 px-4">
        @foreach(['ok' => ['bg-emerald-600', 'check'], 'error' => ['bg-rose-600', 'alert']] as $key => [$bg, $icon])
            @if(session($key))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, {{ strlen(session($key)) > 90 ? 15000 : 5000 }})" x-transition
                     class="pointer-events-auto flex max-w-xl items-start gap-3 rounded-2xl {{ $bg }} px-4 py-3 text-sm text-white shadow-lg">
                    <x-icon :name="$icon" class="mt-0.5 h-5 w-5 shrink-0" /><span class="flex-1">{{ session($key) }}</span>
                    <button @click="show=false" class="opacity-70 hover:opacity-100"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endif
        @endforeach
    </div>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 {{ $isPortal ? 'pb-28 lg:pb-8' : '' }}">
        @if($errors->any() && ! ($hideErrorSummary ?? false))
            <div class="mb-6 flex items-center gap-3 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200"><x-icon name="alert" class="h-5 w-5" /> Some fields need your attention – they are marked in red below.</div>
        @endif
        {{ $slot }}
    </main>
</div>

{{-- Phone tab bar for parents and students --}}
@if($isPortal)
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
        <div class="grid grid-cols-5">
            @foreach(collect($nav[''])->whereIn('icon', ['home', 'award', 'check-square', 'wallet', 'message']) as $i)
                <a href="{{ $i['url'] }}" class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $i['active'] ? 'text-brand-600' : 'text-slate-500' }}">
                    <x-icon :name="$i['icon']" class="h-5 w-5" />{{ $i['label'] === 'Payments' ? 'Payments' : $i['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
@endif
@unless($noChat ?? false)<x-chat />@endunless

{{-- Command palette (Ctrl+K) --}}
<div x-data="palette(@js($palette), @js($canSearch ? route('students.quick') : null))" x-show="open" x-cloak @palette.window="show()" @keydown.escape.window="open=false"
     x-transition.opacity.duration.200ms class="fixed inset-0 z-[60] flex items-start justify-center bg-slate-900/40 p-4 pt-[12vh] backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Command palette">
    <div @click.outside="open=false" x-show="open" x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 scale-95 -translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0" class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10">
        <div class="flex items-center gap-3 border-b border-slate-100 px-4 dark:border-white/10">
            <x-icon name="search" class="h-5 w-5 text-slate-400" />
            <input x-ref="q" x-model="q" @input.debounce.200ms="search()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   class="h-14 w-full border-0 bg-transparent text-base placeholder:text-slate-400 focus:ring-0 focus:outline-none" placeholder="Type a page, action or student name…" aria-label="Search">
            <kbd class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-white/10">Esc</kbd>
        </div>
        <ul class="max-h-[55vh] overflow-y-auto p-2" role="listbox">
            <template x-for="(r, i) in results" :key="r.url + i">
                <li>
                    <template x-if="i === 0 || results[i-1].group !== r.group"><div class="px-3 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" x-text="r.group"></div></template>
                    <a :href="r.url" @mouseenter="sel=i" :class="sel===i ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200' : ''" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm" role="option" :aria-selected="sel===i">
                        <span class="font-medium" x-text="r.label"></span><span class="truncate text-slate-400" x-text="r.hint"></span>
                    </a>
                </li>
            </template>
            <li x-show="!results.length" class="px-3 py-8 text-center text-sm text-slate-500">No matches. Try another word.</li>
        </ul>
    </div>
</div>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    function palette(items, studentUrl) {
        return {
            open: false, q: '', sel: 0, students: [], items,
            get results() {
                const t = this.q.toLowerCase();
                const pages = this.items.filter(i => !t || (i.label + ' ' + i.hint + ' ' + i.group).toLowerCase().includes(t));
                return [...this.students, ...pages].slice(0, 30);
            },
            show() { this.open = true; this.q = ''; this.sel = 0; this.students = []; this.$nextTick(() => this.$refs.q.focus()) },
            move(d) { this.sel = (this.sel + d + this.results.length) % Math.max(this.results.length, 1) },
            go() { const r = this.results[this.sel]; if (r) location.href = r.url },
            async search() {
                this.sel = 0;
                if (!studentUrl || this.q.length < 2) { this.students = []; return }
                const r = await fetch(studentUrl + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                this.students = (await r.json()).map(s => ({ ...s, group: 'Students' }));
            },
        }
    }
</script>
</body>
</html>
