<x-layouts.app title="Promotion">
    <div class="card max-w-2xl">
        <h1 class="h2">End-of-year promotion</h1>
        <p class="muted mt-1">For every student still enrolled in the old school year: general average ≥ {{ $passing }} → promoted to the next grade level; below → retained in the same grade level; passed the last grade level → graduated. Students are enrolled in the new school year without a section.</p>
        <ol class="mt-3 list-decimal pl-5 text-sm text-slate-600">
            <li>Make sure all grades are submitted (Gradebook).</li>
            <li>Create the new school year (Administration → School years).</li>
            <li>Run promotion here, then <a class="link" href="{{ route('allocation.index') }}">assign sections</a> for each grade (random, students choose, or by hand).</li>
        </ol>
        <form method="post" action="{{ route('promotion.run') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            <x-field name="from_year_id" label="From school year" type="select" :options="$years->pluck('name', 'id')" :value="$years->get(1)?->id" required />
            <x-field name="to_year_id" label="To school year" type="select" :options="$years->pluck('name', 'id')" :value="$years->first()?->id" required />
            <fieldset class="sm:col-span-2"><legend class="label">Then put students into sections</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach(['random' => ['Automatically', 'Balanced by seats, boys/girls and grades. Undoable.'], 'choice' => ['Students choose', \App\Support\School::allows('student_section_choice') ? 'Online: 7 days to pick, the rest placed automatically.' : 'Online mode only.'], 'none' => ['Later, by hand', 'Use Assign sections afterwards.']] as $k => [$t, $d])
                        <label class="flex min-h-11 cursor-pointer gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700"><input type="radio" name="sectioning" value="{{ $k }}" class="mt-0.5 h-4 w-4 accent-brand-600" @checked($k === 'random') @disabled($k === 'choice' && ! \App\Support\School::allows('student_section_choice'))>
                            <span><b class="block">{{ $t }}</b><span class="text-slate-600 dark:text-slate-400">{{ $d }}</span></span></label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Sections for the new year must exist first (create them in <a class="link" href="{{ route('allocation.index') }}">Assign sections</a>).</p>
            </fieldset>
            <x-field name="confirm" label="I checked the grades and want to run promotion" type="checkbox" :value="false" class="sm:col-span-2" />
            <div><button class="btn btn-primary">Run promotion</button></div>
        </form>
    </div>
</x-layouts.app>
