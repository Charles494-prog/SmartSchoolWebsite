<x-layouts.app :title="$s->full_name">
    <div class="mb-4 flex flex-wrap items-start gap-3">
        @if($s->photo_path)<img src="{{ route('students.photo', $s) }}" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-slate-200" alt="">@else<div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-100 text-slate-300 ring-1 ring-slate-200" title="No photo yet – add it in Edit"><x-icon name="user" class="h-9 w-9" /></div>@endif
        <div class="flex-1">
            <h1 class="h1">{{ $s->full_name }}</h1>
            <p class="muted">School ID {{ $s->student_no }} · LRN {{ $s->lrn ?: 'not yet entered' }} · {{ $s->sex }} · {{ $s->age }} yrs · <x-status :value="$s->status" /></p>
            @if($current)<p class="mt-1 text-sm">{{ $current->gradeLevel->name }} {{ $current->section ? '– '.$current->section->name : '(no section yet)' }} · S.Y. {{ $current->schoolYear->name ?? '' }}</p>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canEdit)<a href="{{ route('students.edit', $s) }}" class="btn">Edit</a>@endif
            <a href="{{ route('students.id', $s) }}" target="_blank" class="btn">ID card with QR</a>
            @if($current)<a href="{{ route('students.sf9', $current) }}" target="_blank" class="btn">Report card</a>@endif
            @if($canEdit)<a href="{{ route('students.sf10', $s) }}" target="_blank" class="btn">Permanent record</a>
            <a href="{{ route('students.export', $s) }}" class="btn" title="Data Privacy Act: copy of personal data">Export data</a>@endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
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
                                <div class="muted">{{ $g->phone }} {{ $g->email }} {{ $g->user ? '· has portal login' : '' }}</div>
                            </div>
                            @if($canEdit)
                                <form method="post" action="{{ route('students.guardians.remove', [$s, $g]) }}" onsubmit="return confirm('Remove this guardian from the student?')">@csrf @method('delete')<button class="btn btn-sm">Remove</button></form>
                            @endif
                        </div>
                    @empty
                        <p class="muted">No guardian yet.</p>
                    @endforelse
                </div>
                @if($canEdit)
                    <details class="mt-4" @if($errors->hasAny(['name','relationship','email'])) open @endif>
                        <summary class="cursor-pointer font-semibold text-brand-700">+ Add guardian</summary>
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

            @if($report)
                <div class="card overflow-x-auto">
                    <h2 class="h2 mb-3">Grades this school year</h2>
                    <table class="table">
                        <thead><tr><th>Subject</th>@foreach(range(1, $current->gradeLevel->terms) as $t)<th>Q{{ $t }}</th>@endforeach<th>Final</th><th>Remarks</th></tr></thead>
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

            <div class="card">
                <h2 class="h2 mb-2">Attendance (daily)</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    @foreach(\App\Models\AttendanceRecord::STATUSES as $k => $v)<span class="badge bg-slate-100">{{ $v }}: {{ $attendance[$k] ?? 0 }}</span>@endforeach
                </div>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach($recentAttendance as $a)<li>{{ $a->date->format('D, M d') }} – <x-status :value="$a->status" /> {{ ampm($a->time_in) }}</li>@endforeach
                </ul>
            </div>

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
                        <button class="btn btn-danger w-full" onclick="return confirm('Mark this student as transferred out?')">Transfer out</button>
                    </form>
                </details>
                @endif
                @if($s->transfers->isNotEmpty())
                    <div class="card text-sm"><h2 class="h2 mb-2">Transfers</h2>@foreach($s->transfers as $t)<p>{{ $t->transfer_date->format('M d, Y') }} · {{ strtoupper($t->direction) }} · {{ $t->school_name }}</p>@endforeach</div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.app>
