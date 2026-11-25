@php
    $sy = \App\Models\SchoolYear::current();
    $gl = \App\Models\GradeLevel::orderBy('sort_order')->get();
    $secCounts = \App\Models\Section::where('school_year_id', $sy?->id)->selectRaw('grade_level_id, count(*) n')->groupBy('grade_level_id')->pluck('n', 'grade_level_id');
    $roomCounts = \App\Models\Room::whereNotNull('grade_level_id')->selectRaw('grade_level_id, count(*) n')->groupBy('grade_level_id')->pluck('n', 'grade_level_id');
@endphp
<form method="post" action="{{ route('rooms.generate') }}" class="card mb-4 flex flex-wrap items-end gap-3" x-data="{ busy: false }" x-on:submit="busy = true">@csrf
    <div class="min-w-0 flex-1 basis-64">
        <h2 class="font-semibold">Generate rooms for a grade level</h2>
        <p class="text-sm text-slate-600 dark:text-slate-400">Creates one room per section in that grade (e.g. Grade 3 with 2 sections → Room 301, 302). Rooms that already exist are kept.</p>
    </div>
    <label class="block"><span class="label">Grade level</span>
        <select name="grade_level_id" class="input min-h-11" required>
            @foreach($gl as $g)
                <option value="{{ $g->id }}">{{ $g->name }} – {{ $secCounts[$g->id] ?? 0 }} {{ str('section')->plural($secCounts[$g->id] ?? 0) }}, {{ $roomCounts[$g->id] ?? 0 }} {{ str('room')->plural($roomCounts[$g->id] ?? 0) }}</option>
            @endforeach
        </select></label>
    <label class="block"><span class="label">Extra spare rooms</span><input type="number" name="spare" min="0" max="9" value="0" class="input min-h-11 w-24"></label>
    <button class="btn btn-primary min-h-11" :disabled="busy" x-text="busy ? 'Creating…' : 'Generate rooms'">Generate rooms</button>
</form>
