<x-layouts.app title="Set up SmartSchool for you">
    @php
        $keys = array_keys($steps); $first = explode(' ', $u->name)[0];
        $stepIcon = ['hello' => 'sparkles', 'look' => 'settings', 'menu' => 'list', 'classes' => 'clipboard', 'parents' => 'users', 'payments' => 'wallet', 'teaching' => 'book-open', 'family' => 'bell', 'done' => 'check-circle'];
        $stepHint = ['hello' => 'About a minute', 'look' => 'Theme and first page', 'menu' => 'Keep it short', 'classes' => 'To-dos and classwork', 'parents' => 'Who sees your grades', 'payments' => 'Who pays the fees', 'teaching' => 'Notices for you', 'family' => 'What we tell you', 'done' => 'Finish'];
    @endphp
    <form method="post" action="{{ route('welcome.save') }}" class="mx-auto grid max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:grid-cols-[260px_minmax(0,1fr)] dark:border-slate-800 dark:bg-slate-900"
          x-data="{ i: 0, keys: @js($keys), theme: @js($u->pref('theme', 'system')),
                    setTheme(t) { this.theme = t; if (t === 'system') { localStorage.removeItem('theme'); $store.theme.dark = matchMedia('(prefers-color-scheme: dark)').matches } else { localStorage.theme = t; $store.theme.dark = t === 'dark' } document.documentElement.classList.toggle('dark', $store.theme.dark) } }">
        @csrf
        <input type="hidden" name="theme" :value="theme">

        {{-- Steps rail --}}
        <aside class="border-b border-slate-100 bg-slate-50 p-6 md:border-r md:border-b-0 dark:border-slate-800 dark:bg-white/[.02]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Personal setup</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $u->name }}</p>
            <ol class="mt-6 space-y-1">
                @foreach($steps as $k => $label)
                    <li>
                        <button type="button" class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left" :class="keys[i] === '{{ $k }}' ? 'bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700' : ''" x-on:click="i = {{ $loop->index }}">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border"
                                  :class="i > {{ $loop->index }} ? 'border-brand-600 bg-brand-600 text-white' : (i === {{ $loop->index }} ? 'border-brand-600 text-brand-600' : 'border-slate-300 text-slate-400 dark:border-slate-600')">
                                <template x-if="i > {{ $loop->index }}"><x-icon name="check" class="h-4 w-4" /></template>
                                <template x-if="i <= {{ $loop->index }}"><x-icon :name="$stepIcon[$k] ?? 'sparkles'" class="h-4 w-4" /></template>
                            </span>
                            <span class="min-w-0"><span class="block text-sm font-medium" :class="i === {{ $loop->index }} ? 'text-slate-900 dark:text-white' : 'text-slate-500'">{{ $label }}</span><span class="block truncate text-xs text-slate-400">{{ $stepHint[$k] ?? '' }}</span></span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </aside>

        <div class="flex min-h-[480px] flex-col p-6 sm:p-10">
        <div class="flex-1">
            {{-- Welcome --}}
            <section x-show="keys[i] === 'hello'">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-icon name="sparkles" class="h-6 w-6" /></span>
                <h1 class="mt-5 text-2xl font-bold">Hi {{ $first }}! Let's set SmartSchool up for you.</h1>
                <p class="mt-2 text-slate-600 dark:text-slate-300">A few quick choices so the app fits the way you work. It takes about a minute, and you can change everything later in <b>My profile → Personal setup</b>.</p>
            </section>

            {{-- Look & start page --}}
            <section x-show="keys[i] === 'look'" x-cloak class="space-y-6">
                <div>
                    <h2 class="text-xl font-bold">How should it look?</h2>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        @php $svg = [
                            'light' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
                            'dark' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
                            'system' => '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
                        ]; @endphp
                        @foreach(['light' => 'Light', 'dark' => 'Dark', 'system' => 'Same as my device'] as $k => $label)
                            <button type="button" class="flex flex-col items-center gap-2 rounded-xl border-2 p-4 text-sm font-medium" :class="theme === '{{ $k }}' ? 'border-brand-600 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" x-on:click="setTheme('{{ $k }}')">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $svg[$k] !!}</svg>{{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <label class="block">
                    <span class="text-xl font-bold">Which page should open first?</span>
                    <select name="start" class="input mt-3">
                        @foreach($starts as $f)<option @selected($u->pref('start', 'Home') === $f['label'])>{{ $f['label'] }}</option>@endforeach
                    </select>
                </label>
            </section>

            {{-- Menu --}}
            <section x-show="keys[i] === 'menu'" x-cloak>
                <h2 class="text-xl font-bold">Which features will you use?</h2>
                <p class="mt-1 text-sm text-slate-500">Untick what you don't need to keep your menu short. Features with a lock are required by your school.</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach($features as $f)
                        @php $isLocked = in_array($f['label'], $locked, true); @endphp
                        <label class="flex items-start gap-3 rounded-xl border p-3 {{ $isLocked ? 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-white/5' : 'cursor-pointer border-slate-200 has-[:checked]:border-brand-500 dark:border-slate-700' }}">
                            @if($isLocked)
                                <x-icon name="key" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                            @else
                                <input type="checkbox" class="mt-1" x-data x-on:change="$el.nextElementSibling.disabled = $el.checked" @checked(! in_array($f['label'], $hidden, true))>
                                <input type="hidden" name="hidden[]" value="{{ $f['label'] }}" @disabled(! in_array($f['label'], $hidden, true))>
                            @endif
                            <span><span class="block text-sm font-semibold">{{ $f['label'] }}</span><span class="block text-xs text-slate-500">{{ $isLocked ? 'Required by your school' : $f['desc'] }}</span></span>
                        </label>
                    @endforeach
                </div>
            </section>

            @if(isset($steps['classes']))
                <section x-show="keys[i] === 'classes'" x-cloak class="space-y-4">
                    <h2 class="text-xl font-bold">Your classes and to-dos</h2>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><input type="hidden" name="todo_home" value="0"><input type="checkbox" name="todo_home" value="1" class="mt-1" @checked($u->pref('todo_home', true))>
                        <span><b class="block">Show my to-do list on Home</b><span class="text-sm text-slate-500">Everything you haven't turned in yet, with a link straight to it. It stays until you turn it in.</span></span></label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><input type="hidden" name="hide_turned_in" value="0"><input type="checkbox" name="hide_turned_in" value="1" class="mt-1" @checked($u->pref('hide_turned_in'))>
                        <span><b class="block">Hide work I already turned in</b><span class="text-sm text-slate-500">My classes shows everything by default. Tick this to see only what's left.</span></span></label>
                    @unless($online)<p class="text-xs text-slate-500">Class cards appear when your school runs SmartSchool Online.</p>@endunless
                </section>
                <section x-show="keys[i] === 'parents'" x-cloak class="space-y-4">
                    <h2 class="text-xl font-bold">Your parents or guardian</h2>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><input type="hidden" name="live_reports" value="0"><input type="checkbox" name="live_reports" value="1" class="mt-1" @checked($u->pref('live_reports', true))>
                        <span><b class="block">Send my grades to my parents live</b><span class="text-sm text-slate-500">They get a notice each time a teacher scores your work.</span></span></label>
                    @if($parents->isNotEmpty())
                        <p class="text-sm">Already linked: @foreach($parents as $g)<b>{{ $g->name }}</b>@if($g->user) ({{ $g->user->email }})@endif{{ $loop->last ? '' : ', ' }}@endforeach</p>
                    @endif
                    @if(\App\Support\School::allows('parent_accounts'))
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <p class="font-semibold">Give a parent their own account <span class="font-normal text-slate-500">(optional)</span></p>
                            <p class="mb-3 text-sm text-slate-500">They'll see your grades, attendance, class schedule and classes – read only, they can't change anything.</p>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <input name="parent_name" class="input" placeholder="Name">
                                <input name="parent_email" type="email" class="input" placeholder="Email">
                                <select name="parent_relationship" class="input"><option>Mother</option><option>Father</option><option>Guardian</option></select>
                            </div>
                        </div>
                    @endif
                    @if($student)
                        @php $att = \App\Models\AttendanceRecord::where('student_id', $student->id)->whereNull('class_subject_id')->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->get(); @endphp
                        <div class="rounded-xl bg-slate-50 p-4 text-sm dark:bg-white/5"><p class="mb-2 font-semibold">What your parents will see – e.g. your attendance this month</p>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                @foreach(['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent'] as $k => $label)<div class="rounded-lg bg-white p-2 dark:bg-slate-900"><div class="text-xl font-bold">{{ $att->where('status', $k)->count() }}</div><div class="text-xs text-slate-500">{{ $label }}</div></div>@endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endif
            @if(isset($steps['payments']))
                <section x-show="keys[i] === 'payments'" x-cloak class="space-y-3">
                    <h2 class="text-xl font-bold">Who pays your school fees?</h2>
                    @foreach(['parent' => 'My parent / guardian pays (from their account)', 'student' => 'I pay from my own account', 'in_person' => 'We pay in person at the school'] as $k => $label)
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-brand-500 dark:border-slate-700"><input type="radio" name="payer" value="{{ $k }}" @checked($u->pref('payer', 'parent') === $k)> {{ $label }}</label>
                    @endforeach
                </section>
            @endif
            @if(isset($steps['teaching']))
                <section x-show="keys[i] === 'teaching'" x-cloak class="space-y-3">
                    <h2 class="text-xl font-bold">Teaching</h2>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><input type="hidden" name="notify_submissions" value="0"><input type="checkbox" name="notify_submissions" value="1" class="mt-1" @checked($u->pref('notify_submissions', true))>
                        <span><b class="block">Tell me when students turn in work</b><span class="text-sm text-slate-500">A notice in the bell at the top.</span></span></label>
                </section>
            @endif
            @if(isset($steps['family']))
                <section x-show="keys[i] === 'family'" x-cloak class="space-y-3">
                    <h2 class="text-xl font-bold">What should we tell you about?</h2>
                    @foreach(['notify_grades' => 'New grades and scores', 'notify_attendance' => 'Late or absent at the gate'] as $k => $label)
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><input type="hidden" name="{{ $k }}" value="0"><input type="checkbox" name="{{ $k }}" value="1" @checked($u->pref($k, true))> {{ $label }}</label>
                    @endforeach
                    <p class="text-sm text-slate-500">You can see grades, attendance, the class schedule and classes. It's read only – your child's teachers and the school keep the records.</p>
                </section>
            @endif

            <section x-show="keys[i] === 'done'" x-cloak>
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"><x-icon name="check-circle" class="h-6 w-6" /></span>
                <h2 class="mt-5 text-2xl font-bold">You're all set, {{ $first }}!</h2>
                <p class="mt-2 text-slate-600 dark:text-slate-300">Change any of this later in <b>My profile → Personal setup</b>.</p>
            </section>
        </div>

        <div class="mt-8 flex items-center gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
            <button type="button" class="btn" x-show="i > 0" x-on:click="i--">Back</button>
            <button type="submit" class="btn ml-auto" x-show="i < keys.length - 1">Skip for now</button>
            <button type="button" class="btn btn-primary" x-show="i < keys.length - 1" x-on:click="i++">Next</button>
            <button type="submit" class="btn btn-primary ml-auto" x-show="i === keys.length - 1" x-cloak>Finish</button>
        </div>
        </div>
    </form>
</x-layouts.app>
