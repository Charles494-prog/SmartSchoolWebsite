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
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach($levels as $gl)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50"><input type="checkbox" name="offered[]" value="{{ $gl->id }}" checked class="h-4 w-4 rounded"> {{ $gl->name }}</label>
                                @endforeach
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
                            <div x-data="{ ww: {{ $weights->ww_weight ?? 30 }}, pt: {{ $weights->pt_weight ?? 50 }}, qa: {{ $weights->qa_weight ?? 20 }} }" class="space-y-4">
                                @foreach(['ww' => ['Written work', 'Quizzes, seatwork, long tests'], 'pt' => ['Performance tasks', 'Projects, activities, recitation'], 'qa' => ['Quarterly exam', 'The exam at the end of each quarter']] as $k => [$t, $d])
                                    <div class="grid grid-cols-[1fr_6rem] items-center gap-4">
                                        <div><div class="text-sm font-medium text-slate-900">{{ $t }}</div><div class="text-xs text-slate-500">{{ $d }}</div></div>
                                        <div class="relative"><input class="input pr-8" type="number" min="0" max="100" name="{{ $k }}" x-model.number="{{ $k }}" aria-label="{{ $t }} percent"><span class="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-slate-400">%</span></div>
                                    </div>
                                @endforeach
                                <p class="text-sm" :class="ww + pt + qa === 100 ? 'text-emerald-700' : 'text-rose-700'">Total: <b x-text="ww + pt + qa"></b>% <span x-show="ww + pt + qa !== 100">– must be 100%</span></p>
                                <div class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-white/10">
                                    <label class="block"><span class="label">Grading periods</span>
                                        <select name="grading_periods" class="input">@foreach([4 => 'Quarters (4)', 3 => 'Trimesters (3)', 2 => 'Semesters (2)'] as $n => $l)<option value="{{ $n }}" @selected(\App\Support\School::periods() === $n)>{{ $l }}</option>@endforeach</select>
                                    </label>
                                    <label class="block"><span class="label">Top achievers to list</span>
                                        <select name="honors_top_n" class="input">@foreach([10, 15, 20, 30] as $n)<option value="{{ $n }}" @selected((int) (\App\Models\Setting::get('honors_top_n') ?: 10) === $n)>Top {{ $n }}</option>@endforeach</select>
                                    </label>
                                </div>
                                <p class="text-xs text-slate-500">Want to add or rename subjects? Use <a class="link" href="{{ route('crud.index', 'subjects') }}" target="_blank">Subjects</a> after setup.</p>
                            </div>
                            @break

                        @case('staff')
                            @if($staff->isNotEmpty())
                                <div class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-white/5">
                                    <p class="mb-2 font-medium text-slate-700">Already added</p>
                                    <ul class="divide-y divide-slate-200 dark:divide-white/10">
                                        @foreach($staff as $p)
                                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2"><span class="font-medium text-slate-900">{{ $p->name }}</span><span class="text-slate-500">{{ $p->roleLabel() }} · {{ $p->email }}</span>
                                                @unless($p->isAdmin())<button type="submit" form="reset-{{ $p->id }}" class="ml-auto text-sm font-medium text-brand-700 hover:underline">New temporary password</button>@endunless
                                            </li>
                                        @endforeach
                                    </ul>
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
                            @if($sections->isNotEmpty())<div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Already created: {{ $sections->map(fn ($s) => $s->gradeLevel->name.' – '.$s->name)->implode(', ') }}</div>@endif
                            @if($teachers->isEmpty())<p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">No teachers yet – you can still create sections now and pick advisers later.</p>@endif
                            <div x-data="{ rows: [{ name: '', grade_level_id: '{{ $levels->first()?->id }}', adviser_id: '', room: '' }] }" class="space-y-3">
                                <template x-for="(s, i) in rows" :key="i">
                                    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                                        <select class="input" :name="`sections[${i}][grade_level_id]`" x-model="s.grade_level_id" aria-label="Grade level">@foreach($levels as $gl)<option value="{{ $gl->id }}">{{ $gl->name }}</option>@endforeach</select>
                                        <input class="input" :name="`sections[${i}][name]`" x-model="s.name" placeholder="Section name, e.g. Mabini" aria-label="Section name">
                                        <select class="input" :name="`sections[${i}][adviser_id]`" x-model="s.adviser_id" aria-label="Adviser"><option value="">Adviser (optional)</option>@foreach($teachers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                                        <div class="flex gap-2">
                                            <select class="input" :name="`sections[${i}][room]`" x-model="s.room" aria-label="Room"><option value="">Room (optional)</option>@foreach($rooms as $r)<option value="{{ $r->name }}">{{ $r->name }} · {{ $r->capacity }} seats</option>@endforeach</select>
                                            <button type="button" class="btn h-11 w-11 shrink-0 px-0" x-on:click="rows.splice(i, 1)" aria-label="Remove section"><x-icon name="x" class="h-4 w-4" /></button>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-sm" x-on:click="rows.push({ name: '', grade_level_id: '{{ $levels->first()?->id }}', adviser_id: '', room: '' })"><x-icon name="plus" class="h-4 w-4" /> Add section</button>
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
                                <p class="text-sm text-slate-600">Families can apply without logging in – put this on a PC at the entrance or share it: <code class="rounded bg-slate-100 px-1.5 py-0.5">{{ route('apply.create') }}</code></p>
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
