<x-layouts.app title="Promotion">
    <div class="card max-w-2xl">
        <h1 class="h2">End-of-year promotion</h1>
        <p class="muted mt-1">For every student still enrolled in the old school year: general average ≥ {{ $passing }} → promoted to the next grade level; below → retained in the same grade level; passed the last grade level → graduated. Students are enrolled in the new school year without a section.</p>
        <ol class="mt-3 list-decimal pl-5 text-sm text-slate-600">
            <li>Make sure all grades are submitted (Gradebook).</li>
            <li>Create the new school year (Administration → School years).</li>
            <li>Run promotion here, then create sections for the new year and add students.</li>
        </ol>
        <form method="post" action="{{ route('promotion.run') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            <x-field name="from_year_id" label="From school year" type="select" :options="$years->pluck('name', 'id')" :value="$years->get(1)?->id" required />
            <x-field name="to_year_id" label="To school year" type="select" :options="$years->pluck('name', 'id')" :value="$years->first()?->id" required />
            <x-field name="confirm" label="I checked the grades and want to run promotion" type="checkbox" :value="false" class="sm:col-span-2" />
            <div><button class="btn btn-primary">Run promotion</button></div>
        </form>
    </div>
</x-layouts.app>
