<x-layouts.app :title="$s->full_name">
    @php
        $tabs = ['overview' => 'Overview', 'grades' => 'Grades', 'attendance' => 'Attendance', 'account' => 'Account & login'];
        if ($clinic !== null || $guidance !== null) { $tabs['health'] = 'Health & guidance'; }
    @endphp
    <div class="card mb-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            @if($s->photo_path)<img src="{{ route('students.photo', $s) }}" class="h-20 w-20 rounded-full object-cover ring-1 ring-slate-200" alt="">@else<div class="flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-slate-400 ring-1 ring-slate-200" title="No photo yet – add it in Edit"><x-icon name="user" class="h-9 w-9" /></div>@endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2"><h1 class="h1">{{ $s->full_name }}</h1><x-status :value="$s->status" /></div>
                <p class="mt-1 text-sm text-slate-600">{{ $current ? $current->gradeLevel->name.($current->section ? ' – '.$current->section->name : ' (no section yet)').' · S.Y. '.($current->schoolYear->name ?? '') : 'Not enrolled this school year' }}</p>
                <p class="muted mt-1 text-sm">ID {{ $s->student_no }} · LRN {{ $s->lrn ?: 'not entered' }} · {{ $s->sex }} · {{ $s->age }} yrs</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($canEdit)<a href="{{ route('students.edit', $s) }}" class="btn btn-primary">Edit</a>@endif
                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                    <button type="button" class="btn" x-on:click="open = !open" :aria-expanded="open"><x-icon name="download" class="h-4 w-4" /> Documents</button>
                    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:bg-slate-900">
                        <a href="{{ route('students.id', $s) }}" target="_blank" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">ID card with QR</a>
                        @if($current)<a href="{{ route('students.sf9', $current) }}" target="_blank" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Report card (this year)</a>@endif
                        @if($canEdit)
                            <a href="{{ route('students.sf10', $s) }}" target="_blank" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50"><span class="font-medium">Transcript of records (TOR)</span><span class="block text-xs text-slate-500">Every grade level, first to latest</span></a>
                            <a href="{{ route('students.export', $s) }}" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50">Export personal data</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-data="{ tab: (location.hash || '#overview').slice(1) }" x-init="$watch('tab', t => history.replaceState(null, '', '#' + t))">
        <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200" role="tablist">
            @foreach($tabs as $k => $label)
                <button type="button" role="tab" class="-mb-px min-h-11 border-b-2 px-4 text-sm font-medium whitespace-nowrap" :class="tab === '{{ $k }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'" x-on:click="tab = '{{ $k }}'">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'overview'" class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
            <div class="card">
                <h2 class="h2 mb-3">Profile</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    @foreach(['Birthdate' => $s->birthdate->format('M d, Y'), 'Birthplace' => $s->birthplace, 'Address' => $s->address, 'Religion' => $s->religion, 'Nationality' => $s->nationality, 'Mobile' => $s->phone, 'Email' => $s->email, 'Portal login' => $s->user?->email] as $k => $v)
                        <div><dt class="muted">{{ $k }}</dt><dd class="font-medium">{{ $v ?: '—' }}</dd></div>
                    @endforeach
                </dl>
                @if($showMedical && $s->medical_notes)<div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm"><b>Medical notes:</b> {{ $s->medical_notes }}</div>@endif
            </div>

            <div class="card">
                <h2 class="h2 mb-3">Parents / guardians & emergency contacts</h2>
                <div class="space-y-2">
                    @forelse($s->guardians as $g)
                        <div class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm">
                            <div class="flex-1">
                                <b>{{ $g->name }}</b> · {{ $g->pivot->relationship }}
                                @if($g->pivot->is_primary)<span class="badge bg-brand-100 text-brand-700">Primary</span>@endif
                                @if($g->pivot->is_emergency_contact)<span class="badge bg-red-100 text-red-700">Emergency</span>@endif
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                    @if($g->phone)<a href="tel:{{ $g->phone }}" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-200">Call {{ $g->phone }}</a>@else<span class="text-xs font-medium text-amber-700">No phone number – add one so the clinic can call in an emergency</span>@endif
                                    <span class="text-slate-600 dark:text-slate-400">{{ $g->email }} {{ $g->user ? '· has portal login' : '' }}</span>
                                </div>
                            </div>
                            @if($canEdit)
                                <form x-data method="post" action="{{ route('students.guardians.remove', [$s, $g]) }}" x-on:submit="if (!confirm('Remove this guardian from the student?')) $event.preventDefault()">@csrf @method('delete')<button class="btn btn-sm">Remove</button></form>
                            @endif
                        </div>
                    @empty
                        <p class="muted">No guardian yet.</p>
                    @endforelse
                </div>
                @if($canEdit)
                    <details class="mt-4" @if($errors->hasAny(['name','relationship','email'])) open @endif>
                        <summary class="cursor-pointer font-semibold text-brand-700">Add guardian</summary>
                        <form method="post" action="{{ route('students.guardians.add', $s) }}" class="mt-3 grid gap-3 sm:grid-cols-3">
                            @csrf
                            <x-field name="name" label="Full name" required />
                            <x-field name="relationship" label="Relationship" type="select" :options="['Mother' => 'Mother', 'Father' => 'Father', 'Guardian' => 'Guardian', 'Grandparent' => 'Grandparent', 'Sibling' => 'Sibling', 'Other' => 'Other']" required />
                            <x-field name="phone" label="Mobile no." />
                            <x-field name="email" label="Email" type="email" />
                            <x-field name="occupation" label="Occupation" />
                            <div class="space-y-1 pt-6">
                                <x-field name="is_primary" label="Primary guardian" type="checkbox" />
                                <x-field name="is_emergency_contact" label="Emergency contact" type="checkbox" />
                                <x-field name="create_account" label="Create parent portal login (needs email)" type="checkbox" :value="true" />
                            </div>
                            <div class="sm:col-span-3"><button class="btn btn-primary">Add guardian</button></div>
                        </form>
                    </details>
                @endif
            </div>

            </div>
            <div class="space-y-4">
            <div class="card">
                <h2 class="h2 mb-3">Enrollment history</h2>
                <ul class="space-y-2 text-sm">
                    @forelse($s->enrollments->sortByDesc('school_year_id') as $e)
                        <li class="flex items-center justify-between gap-2">
                            <span>{{ $e->schoolYear->name }} · {{ $e->gradeLevel->name }} {{ $e->section ? '– '.$e->section->name : '' }}</span>
                            <span class="flex items-center gap-2"><x-status :value="$e->status" /><a class="link" target="_blank" href="{{ route('students.sf9', $e) }}">Report card</a></span>
                        </li>
                    @empty
                        <li class="muted">Not enrolled yet.</li>
                    @endforelse
                </ul>
                @if($canEdit)
                    <details class="mt-4" @if($errors->hasAny(['school_year_id','grade_level_id','section_id'])) open @endif>
                        <summary class="cursor-pointer font-semibold text-brand-700">Enroll / change section</summary>
                        <form method="post" action="{{ route('students.enroll', $s) }}" class="mt-3 space-y-3">
                            @csrf
                            <x-field name="school_year_id" label="School year" type="select" :options="$years" :value="\App\Models\SchoolYear::current()?->id" required />
                            <x-field name="grade_level_id" label="Grade level" type="select" :options="$levels" :value="$current?->grade_level_id" required />
                            <x-field name="section_id" label="Section" type="select" :options="$sections" :value="$current?->section_id" />
                            <button class="btn btn-primary w-full">Save enrollment</button>
                        </form>
                    </details>
                @endif
            </div>

            </div>
        </div>

        <div x-show="tab === 'grades'" x-cloak class="space-y-4">
            @if($report)
                <div class="card overflow-x-auto">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 class="h2">Grades this school year</h2>@if($canEdit)<a href="{{ route('students.sf10', $s) }}" target="_blank" class="btn btn-sm"><x-icon name="download" class="h-4 w-4" /> Transcript – all grade levels</a>@endif</div>
                    <table class="table">
                        <thead><tr><th>Subject</th>@foreach(range(1, $current->gradeLevel->terms) as $t)<th>{{ \App\Support\School::term($t, $current->gradeLevel->terms) }}</th>@endforeach<th>Final</th><th>Remarks</th></tr></thead>
                        <tbody>
                        @forelse($report['subjects'] as $sub)
                            <tr><td>{{ $sub['name'] }}</td>@foreach($sub['terms'] as $g)<td>{{ $g ?? '' }}</td>@endforeach<td class="font-semibold">{{ $sub['final'] ?? '' }}</td><td>{{ $sub['remark'] }}</td></tr>
                        @empty
                            <tr><td colspan="9" class="muted">No subjects yet (assign a section with classes).</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @if($report['general_average'])<p class="mt-3 font-semibold">General average: {{ $report['general_average'] }} {{ $report['honor'] ? '· '.$report['honor'] : '' }}</p>@endif
                </div>
            @endif

            @if(! $report)<div class="card"><x-empty icon="award" title="No grades this school year" text="Grades appear once the student is in a section and teachers post scores." /></div>@endif
        </div>

        <div x-show="tab === 'attendance'" x-cloak class="max-w-2xl">
            <div class="card">
                <h2 class="h2 mb-2">Attendance (daily)</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    @foreach(\App\Models\AttendanceRecord::STATUSES as $k => $v)<span class="badge bg-slate-100">{{ $v }}: {{ $attendance[$k] ?? 0 }}</span>@endforeach
                </div>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach($recentAttendance as $a)<li>{{ $a->date->format('D, M d') }} – <x-status :value="$a->status" /> {{ ampm($a->time_in) }}</li>@endforeach
                </ul>
            </div>

        </div>

        <div x-show="tab === 'account'" x-cloak class="grid max-w-4xl gap-4 md:grid-cols-2">
            @if($current?->account && auth()->user()->hasRole('admin', 'cashier', 'registrar'))
                <div class="card">
                    <h2 class="h2 mb-1">Account balance</h2>
                    <p class="text-2xl font-bold {{ $current->account->balance() > 0 ? 'text-red-700' : 'text-green-700' }}">{{ \App\Services\Billing::peso($current->account->balance()) }}</p>
                    @if(auth()->user()->hasRole('admin', 'cashier'))<a class="link text-sm" href="{{ route('finance.show', $current) }}">Open statement of account →</a>@endif
                </div>
            @endif

            @if($canEdit)
                <div class="card">
                    <h2 class="h2 mb-2">Student portal login</h2>
                    <form method="post" action="{{ route('students.account', $s) }}" class="space-y-2">
                        @csrf
                        <x-field name="email" label="Login email" type="email" :value="$s->user?->email ?? $s->email" required />
                        <button class="btn w-full">{{ $s->user ? 'Reset password' : 'Create login' }}</button>
                    </form>
                </div>
                @if($s->status === 'active')
                <details class="card">
                    <summary class="cursor-pointer font-semibold text-red-700">Transfer out</summary>
                    <form method="post" action="{{ route('students.transfer', $s) }}" class="mt-3 space-y-2">
                        @csrf
                        <x-field name="school_name" label="Receiving school" required />
                        <x-field name="transfer_date" label="Date" type="date" :value="now()->toDateString()" required />
                        <x-field name="reason" label="Reason" type="textarea" />
                        <button x-data class="btn btn-danger w-full" x-on:click="return confirm('Mark this student as transferred out?')">Transfer out</button>
                    </form>
                </details>
                @endif
                @if($s->transfers->isNotEmpty())
                    <div class="card text-sm"><h2 class="h2 mb-2">Transfers</h2>@foreach($s->transfers as $t)<p>{{ $t->transfer_date->format('M d, Y') }} · {{ strtoupper($t->direction) }} · {{ $t->school_name }}</p>@endforeach</div>
                @endif
            @endif
        </div>

        @if(isset($tabs['health']))
        <div x-show="tab === 'health'" x-cloak class="space-y-4">
            @if($clinic !== null)
                <div class="card">
                    <h2 class="h2 mb-2">Clinic visits <span class="muted">(nurse/admin only)</span></h2>
                    @forelse($clinic as $c)<p class="text-sm">{{ $c->visited_at->format('M d, Y g:i A') }} – {{ $c->complaint }} {{ $c->sent_home ? '(sent home)' : '' }}</p>@empty<p class="muted">None.</p>@endforelse
                </div>
            @endif
            @if($guidance !== null)
                <div class="card">
                    <h2 class="h2 mb-2">Guidance records <span class="muted">(confidential)</span></h2>
                    @forelse($guidance as $g)<p class="text-sm"><a class="link" href="{{ route('crud.edit', ['guidance-records', $g->id]) }}">{{ $g->recorded_on->format('M d, Y') }} – {{ $g->title }}</a></p>@empty<p class="muted">None.</p>@endforelse
                </div>
            @endif
        </div>
        @endif
    </div>
</x-layouts.app>
