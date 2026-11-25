<x-layouts.app title="Enter LRNs">
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="mr-auto">
            <h1 class="h1">Enter LRNs</h1>
            <p class="muted">Staff type or paste each student's 12-digit DepEd LRN here – students never enter it themselves. The same LRN can't be used twice.</p>
        </div>
        <form class="flex gap-2">
            <input type="hidden" name="show" value="{{ $missing ? '' : 'all' }}">
            <input name="q" value="{{ request('q') }}" class="input h-11" placeholder="Find a student">
            <button class="btn h-11">Search</button>
        </form>
    </div>
    <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
        <a href="{{ route('students.lrn') }}" class="rounded-lg px-3 py-1.5 font-medium {{ $missing ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">Missing LRN ({{ $count }})</a>
        <a href="{{ route('students.lrn', ['show' => 'all']) }}" class="rounded-lg px-3 py-1.5 font-medium {{ ! $missing ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">All students</a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($students as $s)
                @php $e = $s->enrollments->sortByDesc('school_year_id')->first(); @endphp
                <li class="flex flex-wrap items-center gap-4 px-5 py-4">
                    <div class="min-w-0 flex-1 basis-56">
                        <a href="{{ route('students.show', $s) }}" class="font-medium hover:underline">{{ $s->full_name }}</a>
                        <p class="text-xs text-slate-500">{{ $s->student_no }}{{ $e?->section ? ' · '.$e->section->label() : '' }}</p>
                    </div>
                    <form method="post" action="{{ route('students.lrn.save', $s) }}" class="flex flex-wrap items-start gap-3">@csrf
                        <x-lrn-input :value="$s->lrn" :ignore="$s->id" :compact="true" />
                        <button class="btn h-11">Save</button>
                    </form>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-slate-500">{{ $missing ? 'Every student has an LRN. 🎉' : 'No students found.' }}</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $students->links() }}</div>
</x-layouts.app>
