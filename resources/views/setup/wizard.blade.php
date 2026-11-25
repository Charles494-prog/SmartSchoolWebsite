@php
    $steps = \App\Http\Controllers\SetupWizardController::steps();
    [$title, $question] = $steps[$step];
    $counted = array_slice(array_keys($steps), 1, -1);
    $pos = array_search($step, $counted, true);
    $pct = $step === 'done' ? 100 : ($pos === false ? 0 : round($pos / count($counted) * 100));
    $S = fn ($k) => \App\Models\Setting::get($k);
    $skip = $next ? route('setup.wizard', $next) : route('dashboard');
    $help = [
        'school' => 'This goes on report cards, receipts and ID cards.',
        'year' => 'Two dates matter here, and they are different: when families can start registering, and the first day students go to class.',
        'levels' => 'Untick the levels you don\'t offer. Levels that already have sections or students are never removed.',
        'rooms' => 'Room capacity is used to warn you when a section has more students than its room fits.',
        'subjects' => 'I already added common subjects for every grade level. Set how grades are weighted – you can still change a single subject later.',
        'staff' => 'Each person gets their own login. Pick what they can open – a teacher only sees their own classes, a guard only sees the gate scanner.',
        'sections' => 'A section is a class of students, like "Grade 7 – Mabini". Give it an adviser and a room. Teachers per subject are assigned afterwards in Classes & sections.',
        'email' => 'Used for absence alerts, announcements, receipts and sign-in codes. Get these from your email provider – Gmail (with an app password), Brevo, Mailgun or your web host. You can skip this and do it later in School settings.',
        'documents' => 'These show on the online application form. Tick "Required" for documents that must be attached before a family can send the form.',
        'fees' => 'Public schools usually charge no tuition. Private schools can use one amount for everyone or a different amount per grade level.',
    ];
@endphp
<x-layouts.app title="Set up your school">
    <div class="{{ $step === 'welcome' ? 'anim-fade-in' : '' }} fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 backdrop-blur-xl sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="wizard-q"
         x-data="{ pct: Number(sessionStorage.getItem('wizardPct') ?? {{ $step === 'welcome' ? 0 : $pct }}), leaving: false }"
         x-init="requestAnimationFrame(() => requestAnimationFrame(() => pct = {{ $pct }})); sessionStorage.setItem('wizardPct', {{ $pct }})"
         x-on:click="if ($event.target.closest('a[href]:not([target])')) leaving = true"
         x-on:submit="leaving = true">
        <div class="{{ $step === 'welcome' ? 'anim-window' : '' }} flex max-h-[100dvh] w-full max-w-2xl flex-col overflow-hidden bg-white/95 shadow-2xl ring-1 ring-slate-900/10 backdrop-blur-2xl transition duration-300 sm:max-h-[90vh] sm:rounded-3xl dark:bg-slate-900/90 dark:ring-white/10"
             :class="leaving && 'opacity-60 scale-[.99]'">
            {{-- Top bar --}}
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 dark:border-white/10">
                <span class="text-sm font-semibold text-slate-900 dark:text-white">School setup</span>
                @if($pos !== false)<span class="text-xs text-slate-500">Step {{ $pos + 1 }} of {{ count($counted) }} · {{ $title }}</span>@endif
                <a href="{{ route('dashboard') }}" class="ml-auto flex h-10 w-10 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close setup (you can come back from Home)"><x-icon name="x" /></a>
            </div>
            <div class="h-1 bg-slate-100 dark:bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><div class="h-1 rounded-r-full bg-brand-600 transition-[width] duration-700" :style="'width: ' + pct + '%'" style="width: {{ $pct }}%"></div></div>

            <div class="anim-stagger flex-1 overflow-y-auto px-5 py-6 sm:px-8">
                {{-- Assistant bubble --}}
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white"><x-icon name="cap" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        @if(session('wizard_ok'))<p class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700"><x-icon name="check" class="h-3.5 w-3.5" /> {{ session('wizard_ok') }}</p>@endif
                        <h1 id="wizard-q" class="text-xl font-semibold text-slate-900 sm:text-2xl">{{ $question }}</h1>
                        @isset($help[$step])<p class="mt-1.5 text-[15px] leading-relaxed text-slate-600">{{ $help[$step] }}</p>@endisset
                    </div>
                </div>

                @if($accounts)
                    <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
                        <p class="text-sm font-semibold text-amber-900">Temporary passwords – shown only once. Write them down or print this page.</p>
                        <ul class="mt-2 divide-y divide-amber-200 text-sm">
                            @foreach($accounts as $a)
                                <li class="flex flex-wrap gap-x-3 py-2"><b>{{ $a['name'] }}</b><span class="text-slate-600">{{ $a['role'] }}</span><span>{{ $a['email'] }}</span><code class="rounded bg-white px-2 font-mono">{{ $a['password'] }}</code></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($errors->any())<div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

                <form id="wizard-form" enctype="multipart/form-data" method="post" action="{{ in_array($step, ['welcome', 'students', 'done']) ? '#' : route('setup.save', $step) }}" class="mt-6 space-y-5">
                    @csrf
                    @switch($step)
                        @case('welcome')
                            <p class="text-[15px] leading-relaxed text-slate-700">Hi {{ explode(' ', auth()->user()->name)[0] }}! I'll walk you through setting up {{ $S('school_name') }} in a few short steps. It takes about 10 minutes, and you can <b>skip any step</b> and come back later.</p>
                            <ol class="grid gap-2 sm:grid-cols-2">
                                @foreach($counted as $i => $k)
                                    <li class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $i + 1 }}</span>{{ $steps[$k][0] }}</li>
                                @endforeach
                            </ol>
                            @break

                        @case('school')
                            <x-field name="school_name" label="School name" :value="$S('school_name') === 'My School' ? '' : $S('school_name')" required autofocus />
                            <x-field name="school_address" label="Address" :value="$S('school_address')" />
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-field name="school_head" label="Principal / school head" :value="$S('school_head')" />
                                <x-field name="school_phone" label="Phone" :value="$S('school_phone')" />
                            </div>
                            <x-field name="school_email" label="School email" type="email" :value="$S('school_email')" />
                            <div x-data="{ preview: @js(\App\Models\Setting::logoUrl()) }">
                                <span class="label">School logo <span class="font-normal text-slate-500">(optional)</span></span>
                                <div class="flex items-center gap-4">
                                    <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-white/10">
                                        <img x-show="preview" x-cloak :src="preview" class="h-full w-full object-contain" alt="Logo preview">
                                        <x-icon name="cap" class="h-6 w-6 text-slate-400" x-show="!preview" />
                                    </span>
                                    <div>
                                        <label class="btn min-h-11 cursor-pointer">Choose logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="sr-only" x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview"></label>
                                        <p class="mt-1.5 text-xs text-slate-500">Square PNG, JPG or WebP, up to 2 MB. Shown on the menu, report cards and the browser tab.</p>
                                        @error('logo')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                            @break

                        @case('year')
                            @php $y0 = now()->month >= 6 ? now()->year : now()->year - 1; @endphp
                            <x-field name="name" label="School year" :value="$year?->name ?? $y0.'-'.($y0 + 1)" required />
                            <div class="rounded-xl border border-slate-200 p-4">
                                <x-field name="enrollment_opens_on" label="Registration opens on" type="date" :value="$year?->enrollment_opens_on?->toDateString() ?? now()->toDateString()" />
                                <p class="mt-1.5 text-xs text-slate-500">The day families can start applying and enrolling – usually weeks <b>before</b> classes start. Today means admission is open now.</p>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <x-field name="starts_on" label="First day of classes" type="date" :value="$year?->starts_on?->toDateString() ?? $y0.'-06-16'" required />
                                    <p class="mt-1.5 text-xs text-slate-500">When students start going to school. Attendance and grading count from here.</p>
                                </div>
                                <div>
                                    <x-field name="ends_on" label="Last day of classes" type="date" :value="$year?->ends_on?->toDateString() ?? ($y0 + 1).'-04-15'" required />
                                </div>
                            </div>
                            @break

                        @case('levels')
                            @php $depts = \App\Models\GradeLevel::DEPARTMENTS; @endphp
                            <div x-data="{ adds: [], programs: [] }" class="space-y-5">
                                @foreach($levels->groupBy('department') as $dept => $group)
                                    <div>
                                        <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $depts[$dept] ?? 'Other' }}</h3>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach($group as $gl)
                                                <div x-data="{ on: true }" class="flex min-h-11 items-center gap-2 rounded-xl border px-3 py-1.5" :class="on ? 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/5' : 'border-dashed border-slate-200 opacity-60 dark:border-white/10'">
                                                    <input type="checkbox" name="offered[]" value="{{ $gl->id }}" x-model="on" class="h-4 w-4 shrink-0 rounded" aria-label="Offer {{ $gl->name }}">
                                                    <input class="min-w-0 flex-1 border-0 bg-transparent px-1 py-1.5 text-sm font-medium text-slate-900 focus:ring-2 focus:ring-brand-500 rounded-md" name="names[{{ $gl->id }}]" value="{{ $gl->name }}" maxlength="60" aria-label="Name of {{ $gl->name }}" :disabled="!on">
                                                    <span class="shrink-0 text-xs text-slate-400" x-show="!on">Not offered</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                                <p class="text-xs text-slate-500">Click a name to rename it. Untick levels you don't offer; levels that already have sections or students are kept.</p>

                                {{-- Add a single level --}}
                                <div class="space-y-2 rounded-xl border border-dashed border-slate-300 p-3 dark:border-white/15">
                                    <p class="text-sm font-semibold text-slate-900">Add a grade level</p>
                                    <template x-for="(l, i) in adds" :key="i">
                                        <div class="grid grid-cols-[1fr_2.75rem] gap-2 sm:grid-cols-[1fr_11rem_2.75rem]">
                                            <input class="input" :name="`new_levels[${i}][name]`" x-model="l.name" placeholder="e.g. Nursery or Grade 13" aria-label="Level name" maxlength="60">
                                            <select class="input col-start-1 sm:col-start-auto" :name="`new_levels[${i}][department]`" x-model="l.department" aria-label="Department">@foreach($depts as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                                            <button type="button" class="btn btn-ghost row-start-1 h-11 w-11 p-0 sm:row-start-auto col-start-2 sm:col-start-auto" x-on:click="adds.splice(i, 1)" aria-label="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                                        </div>
                                    </template>
                                    <button type="button" class="btn btn-sm" x-on:click="adds.push({ name: '', department: 'other' })"><x-icon name="plus" class="h-4 w-4" /> Add a level</button>
                                </div>

                                {{-- College programs --}}
                                <div class="space-y-2 rounded-xl border border-dashed border-slate-300 p-3 dark:border-white/15">
                                    <p class="text-sm font-semibold text-slate-900">Offer college courses or programs</p>
                                    <p class="text-xs text-slate-500">Each program gets a level per year, e.g. "BSIT – 1st Year" to "BSIT – 4th Year". Students move up within their program.</p>
                                    <template x-for="(p, i) in programs" :key="i">
                                        <div class="grid grid-cols-[1fr_2.75rem] gap-2 sm:grid-cols-[1fr_8rem_9rem_2.75rem]">
                                            <input class="input" :name="`programs[${i}][name]`" x-model="p.name" placeholder="e.g. BS Information Technology" aria-label="Program name" maxlength="45">
                                            <input class="input col-start-1 sm:col-start-auto" :name="`programs[${i}][code]`" x-model="p.code" placeholder="Short: BSIT" aria-label="Short name" maxlength="12">
                                            <select class="input col-start-1 sm:col-start-auto" :name="`programs[${i}][years]`" x-model="p.years" aria-label="Years">@foreach([1, 2, 3, 4, 5, 6] as $y)<option value="{{ $y }}">{{ $y }} {{ $y === 1 ? 'year' : 'years' }}</option>@endforeach</select>
                                            <button type="button" class="btn btn-ghost row-start-1 col-start-2 h-11 w-11 p-0 sm:row-start-auto sm:col-start-auto" x-on:click="programs.splice(i, 1)" aria-label="Remove program"><x-icon name="x" class="h-4 w-4" /></button>
                                            <p class="col-span-full -mt-1 text-xs text-slate-500" x-show="p.name || p.code" x-text="'Creates: ' + Array.from({ length: Number(p.years) }, (_, k) => (p.code || p.name) + ' – ' + ['1st','2nd','3rd','4th','5th','6th'][k] + ' Year').join(', ')"></p>
                                        </div>
                                    </template>
                                    <button type="button" class="btn btn-sm" x-on:click="programs.push({ name: '', code: '', years: '4' })"><x-icon name="plus" class="h-4 w-4" /> Add a program</button>
                                    <p class="text-xs text-slate-500">College grades often use 1.00–5.00. Set that per level in the next step (Subjects &amp; grading → Grading scale).</p>
                                </div>
                            </div>
                            @break

                        @case('rooms')
                            <div x-data="{ rows: {{ Js::from($rooms->map(fn ($r) => ['name' => $r->name, 'capacity' => $r->capacity])->values()->whenEmpty(fn ($c) => $c->push(['name' => '', 'capacity' => 40]))) }} }" class="space-y-2">
                                <div class="grid grid-cols-[1fr_7rem_2.75rem] gap-2 text-xs font-semibold text-slate-500"><span>Room name</span><span>Seats</span><span></span></div>
                                <template x-for="(r, i) in rows" :key="i">
                                    <div class="grid grid-cols-[1fr_7rem_2.75rem] gap-2">
                                        <input class="input" :name="`rooms[${i}][name]`" x-model="r.name" placeholder="Room 101" aria-label="Room name">
                                        <input class="input" type="number" min="1" :name="`rooms[${i}][capacity]`" x-model="r.capacity" aria-label="Seats">
                                        <button type="button" class="btn h-11 px-0" x-on:click="rows.splice(i, 1)" aria-label="Remove row"><x-icon name="x" class="h-4 w-4" /></button>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-sm" x-on:click="rows.push({ name: '', capacity: 40 })"><x-icon name="plus" class="h-4 w-4" /> Add room</button>
                            </div>
                            @break

                        @case('subjects')
                            <div x-data="{ ww: {{ $weights->ww_weight ?? 30 }}, pt: {{ $weights->pt_weight ?? 50 }}, qa: {{ $weights->qa_weight ?? 20 }}, at: {{ $weights->at_weight ?? 0 }} }" class="space-y-4">
                                @foreach(['ww' => ['Written work', 'Quizzes, seatwork, long tests'], 'pt' => ['Performance tasks', 'Projects, activities, recitation'], 'qa' => ['Quarterly exam', 'The exam at the end of each quarter'], 'at' => ['Attendance', 'Days present ÷ school days, from daily attendance. Leave 0 to not count it']] as $k => [$t, $d])
                                    <div class="grid grid-cols-[1fr_6rem] items-center gap-4">
                                        <div><div class="text-sm font-medium text-slate-900">{{ $t }}</div><div class="text-xs text-slate-500">{{ $d }}</div></div>
                                        <div class="relative"><input class="input pr-8" type="number" min="0" max="100" name="{{ $k }}" x-model.number="{{ $k }}" aria-label="{{ $t }} percent"><span class="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-slate-400">%</span></div>
                                    </div>
                                @endforeach
                                <p class="text-sm" :class="ww + pt + qa + at === 100 ? 'text-emerald-700' : 'text-rose-700'">Total: <b x-text="ww + pt + qa + at"></b>% <span x-show="ww + pt + qa + at !== 100">– must be 100%</span></p>
                                <div class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-white/10">
                                    <label class="block"><span class="label">Grading periods</span>
                                        <select name="grading_periods" class="input">@foreach([4 => 'Quarters (4)', 3 => 'Trimesters (3)', 2 => 'Semesters (2)'] as $n => $l)<option value="{{ $n }}" @selected(\App\Support\School::periods() === $n)>{{ $l }}</option>@endforeach</select>
                                    </label>
                                    <label class="block"><span class="label">Top achievers to list</span>
                                        <select name="honors_top_n" class="input">@foreach([10, 15, 20, 30] as $n)<option value="{{ $n }}" @selected((int) (\App\Models\Setting::get('honors_top_n') ?: 10) === $n)>Top {{ $n }}</option>@endforeach</select>
                                    </label>
                                </div>
                                <div class="border-t border-slate-100 pt-4 dark:border-white/10">
                                    <span class="label">When do honor titles (With Honors, High, Highest) appear?</span>
                                    <select name="honors_titles_when" class="input">
                                        <option value="each_period" @selected(\App\Models\Setting::get('honors_titles_when') !== 'year_end')>Every grading period</option>
                                        <option value="year_end" @selected(\App\Models\Setting::get('honors_titles_when') === 'year_end')>End of school year only</option>
                                    </select>
                                    @php $hl = array_filter(explode(',', (string) \App\Models\Setting::get('honors_titles_levels'))); @endphp
                                    <p class="mt-2 text-xs text-slate-500">Only for some grade levels (e.g. graduating)? Tick them – leave all unticked for every level.</p>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        @foreach(\App\Models\GradeLevel::orderBy('sort_order')->get() as $gl)
                                            <label class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-sm"><input type="checkbox" name="honors_titles_levels[]" value="{{ $gl->id }}" @checked(in_array((string) $gl->id, $hl, true))> {{ $gl->name }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="border-t border-slate-100 pt-4 dark:border-white/10">
                                    <h3 class="text-sm font-semibold text-slate-900">Grading scale</h3>
                                    <p class="mb-3 text-xs text-slate-500">How grades look: 0–100 like DepEd, 1.00–5.00, a 4.0 GPA, letters, or your own.</p>
                                    @include('partials.grade-scale')
                                </div>
                                <p class="text-xs text-slate-500">Want to add or rename subjects? Use <a class="link" href="{{ route('crud.index', 'subjects') }}" target="_blank">Subjects</a> after setup.</p>
                            </div>
                            @break

                        @case('staff')
                            @if($staff->isNotEmpty())
                                @php $staffJs = $staff->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'role' => $p->roleLabel(), 'email' => $p->email, 'admin' => $p->isAdmin()])->values(); @endphp
                                <div x-data="{ open: false, q: '', role: '', people: @js($staffJs),
                                        get roles() { return [...new Set(this.people.map(p => p.role))].sort() },
                                        get list() { const q = this.q.toLowerCase(); return this.people.filter(p => (!this.role || p.role === this.role) && (!q || (p.name + ' ' + p.email).toLowerCase().includes(q))) },
                                     }" class="rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
                                    <button type="button" class="flex min-h-14 w-full items-center gap-3 px-4 text-left" x-on:click="open = !open" :aria-expanded="open">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><x-icon name="check" class="h-4 w-4" /></span>
                                        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-900">{{ $staff->count() }} staff already have accounts</span>
                                            <span class="block truncate text-xs text-slate-500">{{ $staff->groupBy(fn ($p) => $p->roleLabel())->map(fn ($g, $r) => $g->count().' '.$r)->implode(' · ') }}</span></span>
                                        <span class="text-sm font-medium text-brand-700" x-text="open ? 'Hide' : 'View'"></span>
                                    </button>
                                    <div x-show="open" x-cloak class="border-t border-slate-100 p-3 dark:border-white/10">
                                        <div class="flex flex-wrap gap-2">
                                            <input type="search" class="input min-w-0 flex-1" x-model="q" placeholder="Search name or email" aria-label="Search staff">
                                            <select class="input w-auto" x-model="role" aria-label="Filter by role"><option value="">All roles</option><template x-for="r in roles" :key="r"><option :value="r" x-text="r"></option></template></select>
                                        </div>
                                        <ul class="mt-2 max-h-72 divide-y divide-slate-100 overflow-y-auto dark:divide-white/10">
                                            <template x-for="p in list" :key="p.id">
                                                <li class="flex items-center gap-3 py-2">
                                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-200" x-text="p.name.split(' ').map(w => w[0]).slice(0, 2).join('')"></span>
                                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-slate-900" x-text="p.name"></span><span class="block truncate text-xs text-slate-500" x-text="p.role + ' · ' + p.email"></span></span>
                                                    <button type="submit" x-show="!p.admin" :form="'reset-' + p.id" class="btn btn-sm shrink-0" :aria-label="'New temporary password for ' + p.name">Reset password</button>
                                                </li>
                                            </template>
                                            <li x-show="!list.length" class="py-6 text-center text-sm text-slate-500">No one matches.</li>
                                        </ul>
                                    </div>
                                </div>
                            @endif
                            <div x-data="{ rows: [{ name: '', email: '', role: 'teacher' }] }" class="space-y-3">
                                <template x-for="(p, i) in rows" :key="i">
                                    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_12rem_2.75rem]">
                                        <input class="input" :name="`people[${i}][name]`" x-model="p.name" placeholder="Full name" aria-label="Full name">
                                        <input class="input" type="email" :name="`people[${i}][email]`" x-model="p.email" placeholder="Login email" aria-label="Login email">
                                        <select class="input" :name="`people[${i}][role]`" x-model="p.role" aria-label="What they can open">
                                            @foreach(\App\Support\Role::staffChoices(auth()->user()) as $k => $label)
                                                <option value="{{ $k }}">{{ \App\Support\Role::label($k) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn h-11 px-0" x-on:click="rows.splice(i, 1)" aria-label="Remove person"><x-icon name="x" class="h-4 w-4" /></button>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-sm" x-on:click="rows.push({ name: '', email: '', role: 'teacher' })"><x-icon name="plus" class="h-4 w-4" /> Add person</button>
                            </div>
                            <details class="text-sm text-slate-600"><summary class="cursor-pointer font-medium text-slate-800">What can each role open?</summary>
                                <ul class="mt-2 space-y-1">@foreach(\App\Support\Role::DESCRIPTIONS as $k => $d)<li>{{ $d }}</li>@endforeach</ul>
                            </details>
                            @break

                        @case('sections')
                            @php
                                $secByLevel = $sections->groupBy('grade_level_id')->map(fn ($g) => $g->map(fn ($x) => ['name' => $x->name, 'adviser' => $x->adviser?->name, 'room' => $x->room])->values());
                                $usedRooms = $sections->pluck('room')->filter()->values();
                            @endphp
                            @if($teachers->isEmpty())<p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">No teachers yet. You can create sections now and pick advisers later.</p>@endif
                            <div x-data="{
                                    level: @js((string) $levels->first()?->id),
                                    levels: @js($levels->map(fn ($l) => ['id' => (string) $l->id, 'name' => $l->name])->values()),
                                    existing: @js($secByLevel),
                                    rooms: @js($rooms->pluck('name')->values()),
                                    used: @js($usedRooms),
                                    rows: [],
                                    draft: '',
                                    get levelName() { return (this.levels.find(l => l.id === this.level) || {}).name || '' },
                                    count(id) { return (this.existing[id] || []).length + this.rows.filter(r => r.grade_level_id === id).length },
                                    mine() { return this.rows.map((r, i) => ({ ...r, i })).filter(r => r.grade_level_id === this.level) },
                                    taken(n) { n = n.trim().toLowerCase(); return (this.existing[this.level] || []).some(s => s.name.toLowerCase() === n) || this.mine().some(r => r.name.trim().toLowerCase() === n) },
                                    freeRoom() { const busy = [...this.used, ...this.rows.map(r => r.room)]; return this.rooms.find(r => !busy.includes(r)) || '' },
                                    add(name) { name = (name || '').trim(); if (!name || this.taken(name)) return; this.rows.push({ name, grade_level_id: this.level, adviser_id: '', room: this.freeRoom() }) },
                                    addDraft() { this.draft.split(',').forEach(n => this.add(n)); this.draft = '' },
                                    addLetters(n) { for (const c of 'ABCDEFGHIJ'.slice(0, n)) this.add('Section ' + c) },
                                 }" class="space-y-4">
                                {{-- Grade levels --}}
                                <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Grade levels">
                                    <template x-for="l in levels" :key="l.id">
                                        <button type="button" role="tab" :aria-selected="level === l.id" x-on:click="level = l.id"
                                            class="inline-flex h-11 items-center gap-2 rounded-full px-3.5 text-sm font-medium transition"
                                            :class="level === l.id ? 'bg-brand-600 text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10'">
                                            <span x-text="l.name"></span>
                                            <span class="rounded-full px-2 text-xs" :class="level === l.id ? 'bg-white/20' : (count(l.id) ? 'bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-200' : 'text-slate-400')" x-text="count(l.id) || '–'"></span>
                                        </button>
                                    </template>
                                </div>

                                {{-- Sections of the chosen level --}}
                                <div class="min-w-0 space-y-4 border-t border-slate-100 pt-4 dark:border-white/10">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <h3 class="text-base font-semibold text-slate-900" x-text="levelName"></h3>
                                        <span class="text-xs text-slate-500" x-text="count(level) === 1 ? '1 section' : count(level) + ' sections'"></span>
                                    </div>

                                    {{-- Already saved --}}
                                    <template x-if="(existing[level] || []).length">
                                        <ul class="grid gap-2 sm:grid-cols-2">
                                            <template x-for="s in existing[level]" :key="s.name">
                                                <li class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><x-icon name="check" class="h-4 w-4" /></span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm font-semibold text-slate-900" x-text="s.name"></span>
                                                        <span class="block truncate text-xs text-slate-500" x-text="[s.adviser || 'No adviser yet', s.room ? 'Room ' + s.room : 'No room'].join(' · ')"></span>
                                                    </span>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>

                                    {{-- New ones --}}
                                    <template x-for="r in mine()" :key="r.i">
                                        <div class="rounded-xl border border-brand-200 bg-brand-50/40 p-3 dark:border-brand-500/30 dark:bg-brand-500/5">
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-md bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">New</span>
                                                <input class="input min-w-0 flex-1 font-semibold" x-model="rows[r.i].name" aria-label="Section name">
                                                <button type="button" class="btn btn-ghost h-11 w-11 shrink-0 p-0" x-on:click="rows.splice(r.i, 1)" aria-label="Remove this section"><x-icon name="x" class="h-4 w-4" /></button>
                                            </div>
                                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                <label class="block"><span class="text-xs text-slate-500">Adviser</span>
                                                    <select class="input" x-model="rows[r.i].adviser_id"><option value="">Pick later</option>@foreach($teachers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                                                <label class="block"><span class="text-xs text-slate-500">Room</span>
                                                    <select class="input" x-model="rows[r.i].room"><option value="">Pick later</option>@foreach($rooms as $rm)<option value="{{ $rm->name }}">{{ $rm->name }} · {{ $rm->capacity }} seats</option>@endforeach</select></label>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Add --}}
                                    <div class="rounded-xl border border-dashed border-slate-300 p-3 dark:border-white/15">
                                        <label class="label" for="sec-draft">Add sections to <span x-text="levelName"></span></label>
                                        <div class="flex gap-2">
                                            <input id="sec-draft" class="input min-w-0 flex-1" x-model="draft" x-on:keydown.enter.prevent="addDraft()" placeholder="e.g. Mabini, Rizal, Luna">
                                            <button type="button" class="btn btn-primary shrink-0" x-on:click="addDraft()" :disabled="!draft.trim()"><x-icon name="plus" class="h-4 w-4" /> Add</button>
                                        </div>
                                        <p class="mt-1.5 text-xs text-slate-500">Type one name, or several separated by commas, then press Enter. The next free room is filled in for you.</p>
                                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                            <span class="text-xs text-slate-500">Quick add:</span>
                                            @foreach([2, 3, 4, 5] as $n)<button type="button" class="btn btn-sm" x-on:click="addLetters({{ $n }})">{{ $n }} sections (A–{{ chr(64 + $n) }})</button>@endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- What gets posted --}}
                                <template x-for="(r, i) in rows" :key="'h' + i">
                                    <div class="hidden">
                                        <input type="hidden" :name="`sections[${i}][name]`" :value="r.name">
                                        <input type="hidden" :name="`sections[${i}][grade_level_id]`" :value="r.grade_level_id">
                                        <input type="hidden" :name="`sections[${i}][adviser_id]`" :value="r.adviser_id">
                                        <input type="hidden" :name="`sections[${i}][room]`" :value="r.room">
                                    </div>
                                </template>
                                <p class="text-sm text-slate-600" x-show="rows.length" x-cloak><b x-text="rows.length"></b> new section(s) will be saved when you press <b>Save and continue</b>.</p>
                            </div>
                            @break

                        @case('fees')
                            @php $tv = array_values(array_filter($tuition)); @endphp
                            <div x-data="{ type: @js($S('school_type')), mode: @js($S('tuition_mode')) }" class="space-y-5">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach(['public' => ['Public school', 'No tuition – students pay ₱0.'], 'private' => ['Private school', 'Students pay tuition.']] as $v => [$t, $d])
                                        <label class="flex min-h-11 cursor-pointer gap-3 rounded-xl border p-4" :class="type === '{{ $v }}' ? 'border-brand-600 ring-1 ring-brand-600' : 'border-slate-200'"><input type="radio" name="school_type" value="{{ $v }}" x-model="type" class="mt-1"><span><span class="block text-sm font-semibold">{{ $t }}</span><span class="block text-xs text-slate-500">{{ $d }}</span></span></label>
                                    @endforeach
                                </div>
                                <div x-show="type === 'private'" x-cloak class="space-y-4">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @foreach(['same' => 'Same for every grade level', 'per_level' => 'Different per grade level'] as $v => $t)
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm font-medium" :class="mode === '{{ $v }}' ? 'border-brand-600 ring-1 ring-brand-600' : 'border-slate-200'"><input type="radio" name="tuition_mode" value="{{ $v }}" x-model="mode"> {{ $t }}</label>
                                        @endforeach
                                    </div>
                                    <div x-show="mode === 'same'"><label class="label" for="tuition_all">Tuition per student for the school year (₱)</label><input id="tuition_all" class="input max-w-48" type="number" step="0.01" min="0" name="tuition_all" value="{{ $tv ? max($tv) : '' }}" :disabled="mode !== 'same'"></div>
                                    <div x-show="mode === 'per_level'" class="space-y-2">
                                        @foreach($levels as $gl)
                                            <div class="grid grid-cols-[1fr_10rem] items-center gap-3"><span class="text-sm">{{ $gl->name }}</span><input class="input" type="number" step="0.01" min="0" name="tuition[{{ $gl->id }}]" value="{{ $tuition[$gl->id] ?? '' }}" aria-label="Tuition for {{ $gl->name }}" :disabled="mode !== 'per_level'"></div>
                                        @endforeach
                                    </div>
                                    <p class="text-xs text-slate-500">Other fees (miscellaneous, ID, books) can be added on the Fees page after setup.</p>
                                </div>
                            </div>
                            @break

                        @case('documents')
                            @unless(\App\Support\School::allows('online_application'))
                                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Online applications are off while SmartSchool runs on the school network only (LAN). This list is used if you switch to Hosted later.</p>
                            @endunless
                            <x-document-requirements />
                            @break

                        @case('email')
                            <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                                <x-field name="mail_host" label="SMTP server" :value="$S('mail_host')" placeholder="smtp.gmail.com" />
                                <x-field name="mail_port" label="Port" type="number" :value="$S('mail_port') ?: '587'" />
                            </div>
                            <x-field name="mail_encryption" label="Security" type="select" :options="['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None']" :value="$S('mail_encryption') ?: 'tls'" />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field name="mail_username" label="Username" :value="$S('mail_username')" placeholder="usually your email address" />
                                <div>
                                    <label class="label" for="w_mail_password">Password</label>
                                    <input id="w_mail_password" name="mail_password" type="password" autocomplete="new-password" class="input" placeholder="{{ $S('mail_password') ? 'Saved – type to replace' : 'App password' }}">
                                    <p class="mt-1 text-xs text-slate-500">Stored encrypted.</p>
                                </div>
                                <x-field name="mail_from_address" label="Send from (email)" type="email" :value="$S('mail_from_address')" placeholder="noreply@yourschool.edu.ph" />
                                <x-field name="mail_from_name" label="Send from (name)" :value="$S('mail_from_name')" :placeholder="$S('school_name')" />
                            </div>
                            <fieldset class="space-y-2">
                                <legend class="label">Two-step sign-in (a code sent by email after the password)</legend>
                                @foreach(\App\Support\TwoStep::OPTIONS as $k => $label)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl p-3 ring-1 ring-slate-200 has-[:checked]:ring-2 has-[:checked]:ring-brand-500">
                                        <input type="radio" name="two_step" value="{{ $k }}" @checked(old('two_step', $S('two_step') ?: 'off') === $k)>
                                        <span class="text-sm font-medium text-slate-800">{{ $label }}</span>
                                    </label>
                                @endforeach
                                @error('two_step')<p class="field-error">{{ $message }}</p>@enderror
                                <p class="text-xs text-slate-500">Turn this on only after email works – otherwise people can't receive their code.</p>
                            </fieldset>
                            @break

                        @case('students')
                            <div class="grid gap-3">
                                @foreach([
                                    ['inbox', 'Online applications', 'Families fill in the application form. The registrar approves it with one click and the student is enrolled.', route('applications.index')],
                                    ['users', 'Add students yourself', 'For students already in your school: add them one by one in Students.', route('students.create')],
                                    ['qr', 'Print QR ID cards', 'Once students have a section, print their ID cards. They scan them at the gate and in class.', route('qr.cards')],
                                ] as [$icon, $t, $d, $url])
                                    <a href="{{ $url }}" target="_blank" class="flex gap-4 rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><x-icon :name="$icon" class="h-5 w-5" /></span>
                                        <span><span class="block text-sm font-semibold text-slate-900">{{ $t }}</span><span class="block text-sm text-slate-600">{{ $d }}</span></span>
                                    </a>
                                @endforeach
                                @php $applyUrl = \App\Support\School::shareableUrl(route('apply.create')); @endphp
                                @php $isLan = \App\Support\School::mode() === \App\Support\School::LAN; @endphp
                                <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                                    <p class="text-sm font-semibold text-slate-900">Two ways families can apply</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="flex gap-3 rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                                            <x-icon name="monitor" class="mt-0.5 h-5 w-5 shrink-0 text-brand-700 dark:text-brand-300" />
                                            <div class="text-sm"><p class="font-medium text-slate-900">At school, near the Registrar</p><p class="mt-0.5 text-slate-600 dark:text-slate-300">For families without a phone or who aren't comfortable online. Set up one or two computers near the Registrar with the form open. Your IT person or any staff can do it.</p></div>
                                        </div>
                                        <div class="flex gap-3 rounded-lg bg-slate-50 p-3 {{ $isLan ? 'opacity-70' : '' }} dark:bg-white/5">
                                            <x-icon name="home" class="mt-0.5 h-5 w-5 shrink-0 text-brand-700 dark:text-brand-300" />
                                            <div class="text-sm"><p class="font-medium text-slate-900">From home</p><p class="mt-0.5 text-slate-600 dark:text-slate-300">{{ $isLan ? 'Only on the school Wi-Fi while SmartSchool runs offline. Switch to Online mode to allow this.' : 'Share the link on your Facebook page or group chat. No login needed.' }}</p></div>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="label">Application form link</span>
                                        <div class="flex gap-2" x-data="{ copied: false }">
                                            <code class="input flex min-w-0 flex-1 items-center overflow-x-auto font-mono text-sm whitespace-nowrap">{{ $applyUrl }}</code>
                                            <button type="button" class="btn shrink-0" x-on:click="navigator.clipboard.writeText(@js($applyUrl)); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? 'Copied' : 'Copy'"></span></button>
                                        </div>
                                        <p class="mt-1.5 text-xs text-slate-500">On the Registrar's computer: open the link, then press F11 for full screen so it works like a kiosk.</p>
                                    </div>
                                </div>
                            </div>
                            @break

                        @case('done')
                            <p class="text-[15px] leading-relaxed text-slate-700">Your school is ready. Everything you set here can be changed later in <b>School settings</b> and the pages in the sidebar.</p>
                            @break
                    @endswitch
                </form>
            </div>

            @if($step === 'staff')
                @foreach($staff as $p)<form id="reset-{{ $p->id }}" method="post" action="{{ route('setup.reset', $p) }}" class="hidden">@csrf</form>@endforeach
            @endif
            {{-- Footer actions --}}
            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-white/10 dark:bg-white/5">
                @if($prev && $step !== 'done')<a href="{{ route('setup.wizard', $prev) }}" class="btn">Back</a>@endif
                <div class="ml-auto flex gap-2">
                    @if($step === 'done')
                        <form method="post" action="{{ route('setup.finish') }}">@csrf<button class="btn btn-primary">Go to my dashboard</button></form>
                    @elseif(in_array($step, ['welcome', 'students']))
                        @if($step === 'welcome')<form method="post" action="{{ route('setup.finish') }}">@csrf<button class="btn">I'll set up later</button></form>@endif
                        <a href="{{ $skip }}" class="btn btn-primary">{{ $step === 'welcome' ? "Let's go" : 'Continue' }}</a>
                    @elseif($accounts)
                        <a href="{{ $skip }}" class="btn btn-primary">I wrote them down – continue</a>
                    @else
                        <a href="{{ $skip }}" class="btn">Skip</a>
                        <button form="wizard-form" class="btn btn-primary">Save and continue</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
