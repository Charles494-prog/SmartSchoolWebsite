<x-layouts.app title="Gradebook">
    <x-page-header :title="auth()->user()->role === 'teacher' ? 'My classes & grades' : 'Grades'" :subtitle="auth()->user()->role === 'teacher' ? 'Open a class to enter scores. When a grading period is done, submit it – the registrar approves it and parents can see it.' : 'Each row is one subject in one section. Teachers enter scores; you approve each grading period.'" />
    @if($weightRequests->isNotEmpty())
        <div class="card mb-4">
            <h2 class="h2 mb-3">Grading changes waiting for your approval</h2>
            <div class="space-y-3">
                @foreach($weightRequests as $r)
                    @php $old = $r->classSubject->weights(); $names = ['ww' => 'Quizzes', 'pt' => 'Projects', 'qa' => 'Exams', 'at' => 'Attendance']; @endphp
                    <div class="rounded-xl p-4 ring-1 ring-slate-200" x-data="{ no:false }">
                        <div class="flex flex-wrap items-center gap-2"><div class="flex-1"><b>{{ $r->classSubject->subject->name }}</b> · {{ $r->classSubject->section->label() }} <span class="text-slate-500">· {{ $r->requester->name }} · {{ $r->created_at->diffForHumans() }}</span></div></div>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm">
                            @foreach($r->weights() as $k => $v)
                                <span class="rounded-lg px-2.5 py-1 {{ $v != $old[$k] ? 'bg-amber-50 ring-1 ring-amber-200' : 'bg-slate-50' }}">{{ $names[$k] }}: @if($v != $old[$k])<s class="text-slate-400">{{ $old[$k] }}%</s> → @endif<b>{{ $v }}%</b></span>
                            @endforeach
                        </div>
                        <p class="mt-2 text-sm text-slate-600">“{{ $r->reason }}”</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="post" action="{{ route('gradebook.weights.review', $r) }}">@csrf<input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-sm">Approve</button></form>
                            <button @click="no=!no" class="btn btn-sm">Decline…</button>
                            <form x-show="no" x-cloak method="post" action="{{ route('gradebook.weights.review', $r) }}" class="flex w-full gap-2">@csrf<input type="hidden" name="action" value="reject"><input name="note" class="input" placeholder="Reason (optional)"><button class="btn btn-sm">Decline</button></form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @if($pending->isNotEmpty())
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200">
            <x-icon name="clock" class="h-5 w-5 shrink-0" /><span class="flex-1">{{ $pending->count() }} {{ str('class')->plural($pending->count()) }} waiting for your approval.</span>
            <a href="{{ route('approvals.index') }}" class="btn btn-primary min-h-11">Open approval queue</a>
        </div>
    @endif
    @php
        $state = function ($c, $t) {
            $sub = $c->submissions->firstWhere('term', $t);
            return match (true) {
                $sub?->status === 'approved' => ['Approved', 'bg-emerald-500', 'text-emerald-700'],
                $sub?->status === 'submitted' => ['Submitted', 'bg-sky-500', 'text-sky-700'],
                $sub?->status === 'returned' => ['Returned', 'bg-rose-500', 'text-rose-700'],
                ($c->{'t'.$t} ?? 0) > 0 => ['In progress', 'bg-amber-400', 'text-amber-700'],
                default => ['Not started', 'bg-slate-300', 'text-slate-400'],
            };
        };
    @endphp
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('gradebook.index') }}" class="rounded-full px-4 py-1.5 text-sm font-medium {{ ! request('level') ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">All grades</a>
        @foreach($levels as $id => $name)
            <a href="{{ route('gradebook.index', ['level' => $id]) }}" class="rounded-full px-4 py-1.5 text-sm font-medium {{ request('level') == $id ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $name }}</a>
        @endforeach
        @if($sectionsList->count() > 1)
            <select x-data class="input ml-auto w-auto py-1.5" x-on:change="location='?level={{ request('level') }}&section='+$el.value"><option value="">All sections</option>@foreach($sectionsList as $k => $v)<option value="{{ $k }}" @selected(request('section') == $k)>{{ $v }}</option>@endforeach</select>
        @endif
    </div>
    <div class="mb-4 flex flex-wrap gap-4 text-xs text-slate-500">
        @foreach([['Not started', 'bg-slate-300'], ['In progress', 'bg-amber-400'], ['Submitted', 'bg-sky-500'], ['Approved', 'bg-emerald-500'], ['Returned', 'bg-rose-500']] as [$l, $c])<span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {{ $c }}"></span>{{ $l }}</span>@endforeach
    </div>

    @if(auth()->user()->role === 'teacher')
        {{-- Teacher: one card per class --}}
        @php $tone = ['bg-indigo-700', 'bg-emerald-700', 'bg-sky-700', 'bg-amber-600', 'bg-rose-700', 'bg-fuchsia-700', 'bg-teal-700', 'bg-slate-800']; @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($classes as $c)
                @php $terms = $c->section->gradeLevel->terms ?: 4; $now = collect(range(1, $terms))->first(fn ($t) => $state($c, $t)[0] !== 'Approved') ?? $terms; @endphp
                <a href="{{ route('gradebook.show', [$c, 'term' => $now]) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div class="px-5 py-4 text-white {{ $tone[crc32($c->subject->name) % count($tone)] }}">
                        <p class="text-lg font-bold group-hover:underline">{{ $c->subject->name }}</p>
                        <p class="text-sm text-white/85">{{ $c->section->label() }}</p>
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-5">
                        <div class="grid gap-1.5" style="grid-template-columns: repeat({{ $terms }}, minmax(0, 1fr))">
                            @foreach(range(1, $terms) as $t)
                                @php [$label, $dot, $txt] = $state($c, $t); @endphp
                                <div class="rounded-lg bg-slate-50 px-2 py-1.5 text-center dark:bg-white/5" title="{{ \App\Support\School::term($t, $terms) }}: {{ $label }}">
                                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ \App\Support\School::term($t, $terms) }}</div>
                                    <div class="mx-auto mt-1 h-1.5 w-full rounded-full {{ $dot }}"></div>
                                </div>
                            @endforeach
                        </div>
                        @php [$nl] = $state($c, $now); @endphp
                        <p class="mt-auto text-sm text-slate-600 dark:text-slate-300">{{ \App\Support\School::periodName($terms) }} {{ $now }} · <b>{{ $nl }}</b></p>
                        <span class="text-sm font-semibold text-brand-700 dark:text-brand-300">Enter scores →</span>
                    </div>
                </a>
            @empty
                <p class="card text-slate-500 sm:col-span-3">No classes assigned to you yet.</p>
            @endforelse
        </div>
    @else
    @forelse($classes->groupBy('section_id') as $group)
        @php $sec = $group->first()->section; @endphp
        <div class="card mb-6 p-0">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 dark:border-white/10">
                <h2 class="flex-1 font-semibold text-slate-900">{{ $sec->label() }}</h2>
                <span class="text-sm text-slate-500">{{ $group->count() }} subjects</span>
            </div>
            <table class="table">
                <thead><tr><th>Subject</th><th>Teacher</th>@foreach(range(1, $sec->gradeLevel->terms) as $t)<th class="text-center">{{ \App\Support\School::term($t, $sec->gradeLevel->terms) }}</th>@endforeach<th></th></tr></thead>
                <tbody>
                @foreach($group as $c)
                    <tr x-data class="cursor-pointer" x-on:click="location='{{ route('gradebook.show', $c) }}'">
                        <td class="font-medium">{{ $c->subject->name }}</td>
                        <td class="{{ $c->teacher ? '' : 'text-slate-400' }}">{{ $c->teacher?->name ?? 'No teacher' }}</td>
                        @foreach(range(1, $sec->gradeLevel->terms) as $t)
                            @php [$label, $dot, $txt] = $state($c, $t); @endphp
                            <td class="text-center"><span class="inline-flex items-center gap-1.5 text-xs {{ $txt }}" title="{{ \App\Support\School::term($t, $sec->gradeLevel->terms) }}: {{ $label }}"><span class="h-2 w-2 rounded-full {{ $dot }}"></span><span class="hidden xl:inline">{{ $label }}</span></span></td>
                        @endforeach
                        <td class="text-right"><a href="{{ route('gradebook.show', $c) }}" class="link text-sm">Open</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="card"><x-empty icon="book-open" title="No classes here" text="A class appears here once a subject in a section has a teacher."
                @if(auth()->user()->hasRole('admin', 'registrar')) action="Open Classes & sections" :href="route('sections.index')" @endif
                :steps="['Open <b>Classes &amp; sections</b> and click a section.', 'In <b>Subjects &amp; teachers</b>, add a subject and choose its teacher.', 'The class then shows here, where scores are entered.']" /></div>
    @endforelse
    @endif
</x-layouts.app>
