<div id="grade-scale" class="scroll-mt-24"></div>
{{-- Grading scale editor (Settings + setup wizard). Posts grade_scale[i][min|label|desc] and grade_scale_show. --}}
@php
    $gsLevels = \App\Models\GradeLevel::orderBy('sort_order')->get(['id', 'name', 'grade_scale']);
    $gsLevel = $gsLevels->firstWhere('id', (int) request('scale_level'));
    $gsRows = old('grade_scale', \App\Support\GradeScale::rows($gsLevel?->id));
    $gsPresets = collect(\App\Support\GradeScale::PRESETS)->map(fn ($p, $k) => ['name' => $p[0], 'rows' => \App\Support\GradeScale::preset($k)]);
    $gsPass = (float) old('passing_grade', \App\Support\GradeScale::passing($gsLevel?->id));
@endphp
<div x-data="{
        rows: @js(array_values($gsRows)),
        presets: @js($gsPresets),
        pass: @js($gsPass),
        add() { this.rows.push({ min: '', label: '', desc: '' }) },
        remove(i) { if (this.rows.length > 2) this.rows.splice(i, 1) },
        use(k) { if (k) this.rows = JSON.parse(JSON.stringify(this.presets[k].rows)) },
        sorted() { return [...this.rows].filter(r => r.min !== '' && r.min !== null).sort((a, b) => b.min - a.min) },
        upper(i) { const s = this.sorted(); const r = this.rows[i]; const k = s.indexOf(r); return k <= 0 ? 100 : s[k - 1].min - 0.01 },
        problem() {
            const m = this.rows.map(r => Number(r.min));
            if (this.rows.some(r => r.min === '' || r.min === null)) return 'Every row needs a lowest score.';
            if (new Set(m).size !== m.length) return 'Two rows start at the same score.';
            if (Math.min(...m) !== 0) return 'One row must start at 0 so every grade has a place.';
            return '';
        },
     }" class="space-y-3">
    <input type="hidden" name="grade_scale_level" value="{{ $gsLevel?->id }}">
    @if($gsLevel)<input type="hidden" name="passing_grade" value="{{ \App\Models\Setting::get('passing_grade') ?: 75 }}">@endif
    <div>
        <span class="label">Which grade levels use this scale?</span>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ request()->fullUrlWithQuery(['scale_level' => null]) }}#grade-scale" class="inline-flex h-9 items-center rounded-full px-3 text-sm font-medium {{ $gsLevel ? 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200' : 'bg-brand-600 text-white' }}">Whole school</a>
            @foreach($gsLevels as $gl)
                <a href="{{ request()->fullUrlWithQuery(['scale_level' => $gl->id]) }}#grade-scale" class="inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium {{ $gsLevel?->id === $gl->id ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200' }}">{{ $gl->name }}@if(! empty($gl->grade_scale))<span class="h-1.5 w-1.5 rounded-full {{ $gsLevel?->id === $gl->id ? 'bg-white' : 'bg-brand-600' }}" title="Has its own scale"></span>@endif</a>
            @endforeach
        </div>
        <p class="mt-1.5 text-xs text-slate-500">
            @if($gsLevel)
                @if(! empty($gsLevel->grade_scale)) <b>{{ $gsLevel->name }}</b> has its own scale. @else <b>{{ $gsLevel->name }}</b> uses the whole-school scale now. Edit below and save to give it its own scale and passing score. Useful for college programs. @endif
            @else
                Levels with a dot have their own scale; the rest use this one.
            @endif
        </p>
        @if($gsLevel && ! empty($gsLevel->grade_scale))
            <label class="mt-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="grade_scale_reset" value="1"> Go back to the whole-school scale for {{ $gsLevel->name }}</label>
        @endif
    </div>
    <div class="flex flex-wrap items-end gap-3">
        <label class="block min-w-0 flex-1"><span class="label">Start from a ready-made scale</span>
            <select class="input" x-on:change="use($event.target.value); $event.target.value = ''">
                <option value="">Choose a scale…</option>
                @foreach(\App\Support\GradeScale::PRESETS as $k => [$name])<option value="{{ $k }}">{{ $name }}</option>@endforeach
            </select>
        </label>
        <label class="block w-full sm:w-64"><span class="label">On grades and report cards, show</span>
            <select name="grade_scale_show" class="input">
                @foreach(\App\Support\GradeScale::SHOW as $k => $l)<option value="{{ $k }}" @selected(old('grade_scale_show', \App\Support\GradeScale::show()) === $k)>{{ $l }}</option>@endforeach
            </select>
        </label>
    </div>
    <p class="text-xs text-slate-500">Grades are always computed out of 100. Each row says what a score range is shown as. Leave "Shown as" empty to show the number itself.</p>

    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
        <div class="hidden grid-cols-[7rem_6rem_1fr_6rem_2.75rem] gap-2 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 sm:grid dark:bg-white/5 dark:text-slate-300">
            <span>Score range</span><span>Shown as</span><span>Description</span><span>Result</span><span class="sr-only">Remove</span>
        </div>
        <template x-for="(r, i) in rows" :key="i">
            <div class="grid grid-cols-[1fr_1fr_2.75rem] gap-2 border-t border-slate-100 px-3 py-2 first:border-t-0 sm:grid-cols-[7rem_6rem_1fr_6rem_2.75rem] sm:items-center dark:border-white/10">
                <label class="block"><span class="text-xs text-slate-500 sm:sr-only">From score</span>
                    <div class="flex items-center gap-1"><input type="number" step="0.01" min="0" max="100" class="input" :name="`grade_scale[${i}][min]`" x-model.number="r.min" aria-label="Lowest score in this range"><span class="hidden text-xs whitespace-nowrap text-slate-400 lg:inline" x-text="r.min !== '' ? '–' + Math.round(upper(i)) : ''"></span></div>
                </label>
                <label class="block"><span class="text-xs text-slate-500 sm:sr-only">Shown as</span>
                    <input type="text" maxlength="12" class="input" :name="`grade_scale[${i}][label]`" x-model="r.label" placeholder="number" aria-label="Shown as">
                </label>
                <button type="button" class="btn btn-ghost row-span-2 h-11 w-11 self-end p-0 sm:order-last sm:row-span-1 sm:self-auto" x-on:click="remove(i)" :disabled="rows.length <= 2" aria-label="Remove this row"><x-icon name="x" class="h-4 w-4" /></button>
                <label class="col-span-2 block sm:col-span-1"><span class="text-xs text-slate-500 sm:sr-only">Description</span>
                    <input type="text" maxlength="40" class="input" :name="`grade_scale[${i}][desc]`" x-model="r.desc" placeholder="e.g. Very Good" aria-label="Description">
                </label>
                <span class="hidden text-xs font-semibold sm:block" :class="r.min >= pass ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" x-text="r.min === '' ? '' : (r.min >= pass ? 'Passed' : 'Failed')"></span>
            </div>
        </template>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <button type="button" class="btn btn-sm" x-on:click="add()" :disabled="rows.length >= 20"><x-icon name="plus" class="h-4 w-4" /> Add a row</button>
        <label class="flex items-center gap-2 text-sm"><span class="font-medium text-slate-700 dark:text-slate-200">Passing score{{ $gsLevel ? ' for '.$gsLevel->name : '' }}</span>
            <input type="number" name="{{ $gsLevel ? 'level_passing_grade' : 'passing_grade' }}" min="50" max="100" step="0.01" class="input w-24" x-model.number="pass" aria-label="Passing score out of 100">
        </label>
    </div>
    <p class="text-sm text-rose-700 dark:text-rose-400" x-show="problem()" x-text="problem()" role="alert"></p>
    @error('grade_scale')<p class="field-error">{{ $message }}</p>@enderror
    @error('grade_scale.*')<p class="field-error">{{ $message }}</p>@enderror
</div>
