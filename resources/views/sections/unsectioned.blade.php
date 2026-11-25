<x-layouts.app title="Students without a section">
    <x-page-header title="Students without a section" subtitle="These students are enrolled but not in a section yet. Pick a section for each one, then save." :back="route('sections.index')" back-label="Classes & sections" />
    @if($students->isEmpty())
        <div class="card"><x-empty icon="check-circle" title="Everyone has a section" text="All enrolled students are placed."><a href="{{ route('sections.index') }}" class="btn btn-primary min-h-11">Back to Classes &amp; sections</a></x-empty></div>
    @else
        <form method="post" action="{{ route('sections.unsectioned.place') }}" class="space-y-5" x-data="{ busy: false }" x-on:submit="busy = true">@csrf
            @foreach($students->groupBy('grade_level_id') as $levelId => $group)
                @php $opts = $sections[$levelId] ?? collect(); @endphp
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 font-semibold dark:border-slate-800">
                        {{ $group->first()->gradeLevel->name }} <span class="text-sm font-normal text-slate-500">{{ $group->count() }} {{ str('student')->plural($group->count()) }}</span></h2>
                    @if($opts->isEmpty())
                        <p class="flex flex-wrap items-center gap-3 p-5 text-sm text-amber-900 dark:text-amber-200"><x-icon name="alert" class="h-5 w-5" /> There is no {{ $group->first()->gradeLevel->name }} section yet.
                            <a href="{{ route('sections.index', ['level' => $levelId]) }}" class="btn btn-primary min-h-11">Create a section</a></p>
                    @else
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($group as $e)
                                <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                                    <div class="min-w-0 flex-1 basis-48"><p class="font-medium break-words">{{ $e->student->full_name }}</p><p class="text-xs text-slate-500">{{ $e->student->student_no }}{{ $e->student->gender ? ' · '.ucfirst($e->student->gender) : '' }}</p></div>
                                    <label class="sr-only" for="p{{ $e->id }}">Section for {{ $e->student->full_name }}</label>
                                    <select id="p{{ $e->id }}" name="place[{{ $e->id }}]" class="input min-h-11 w-full sm:w-72">
                                        <option value="">Choose a section…</option>
                                        @foreach($opts as $s)
                                            <option value="{{ $s->id }}" @selected(old("place.{$e->id}") == $s->id) @disabled($s->enrollments_count >= $s->capacity)>{{ $s->name }} – {{ $s->enrollments_count }}/{{ $s->capacity }}{{ $s->enrollments_count >= $s->capacity ? ' (full)' : '' }}</option>
                                        @endforeach
                                    </select>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
            <div class="sticky bottom-0 flex justify-end border-t border-slate-200 bg-white/90 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
                <button class="btn btn-primary min-h-11" :disabled="busy" x-text="busy ? 'Saving…' : 'Save placements'">Save placements</button>
            </div>
        </form>
    @endif
</x-layouts.app>
