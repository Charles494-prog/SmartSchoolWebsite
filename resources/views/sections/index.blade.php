<x-layouts.app title="Classes & sections" :hideErrorSummary="true">
    <div x-data="{ drawer: {{ $errors->any() ? 'true' : 'false' }} }">
        <x-page-header title="Classes & sections" :subtitle="'School year '.($sy?->name ?? '—').' · '.$sections->count().' section(s)'">
            <form><select name="school_year_id" class="input py-2" onchange="this.form.submit()">@foreach($years as $k => $v)<option value="{{ $k }}" @selected($sy?->id == $k)>S.Y. {{ $v }}</option>@endforeach</select></form>
            <a href="{{ route('timetable', ['room' => '__all']) }}" class="btn"><x-icon name="clock" class="h-4 w-4" /> Room schedules</a>
            @if($sy)<button @click="drawer=true" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> New section</button>@endif
        </x-page-header>

        @if(! $sy)
            <div class="card"><x-empty icon="calendar" title="No school year yet" text="Create the current school year first."><a href="{{ route('crud.index', 'school-years') }}" class="btn btn-primary btn-sm">Set up school year</a></x-empty></div>
        @else
            @if($unsectioned)
                <div class="mb-6 flex items-center gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"><x-icon name="alert" class="h-5 w-5" /> {{ $unsectioned }} enrolled student(s) still need a section. Open a section and use “Add students”.</div>
            @endif

            {{-- Grade level filter --}}
            <div class="mb-6 flex flex-wrap gap-2">
                <a href="{{ request()->fullUrlWithQuery(['level' => null]) }}" class="rounded-full px-4 py-1.5 text-sm font-medium {{ ! $level ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">All grades</a>
                @foreach($levelTabs as $id => $t)
                    <a href="{{ request()->fullUrlWithQuery(['level' => $id]) }}" class="rounded-full px-4 py-1.5 text-sm font-medium {{ (string) $level === (string) $id ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $t['name'] }} <span class="ml-1 opacity-60">{{ $t['count'] }}</span></a>
                @endforeach
            </div>

            @forelse($sections->groupBy(fn ($s) => $s->gradeLevel->name) as $levelName => $group)
                <h2 class="mb-3 text-sm font-semibold text-slate-500">{{ $levelName }}</h2>
                <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($group as $s)
                        @php $pct = $s->capacity ? min(100, round($s->enrollments_count / $s->capacity * 100)) : 0; @endphp
                        <a href="{{ route('sections.show', $s) }}" class="card group block p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <div><div class="text-lg font-semibold text-slate-900">{{ $s->name }}</div><div class="text-sm text-slate-500">{{ $s->adviser?->name ? 'Adviser: '.$s->adviser->name : 'No adviser yet' }}</div></div>
                                <x-icon name="chevron" class="h-5 w-5 text-slate-300 group-hover:text-brand-500" />
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="badge bg-slate-100 text-slate-600"><x-icon name="home" class="h-3.5 w-3.5" /> {{ $s->room ?: 'No room' }}</span>
                                <span class="badge bg-slate-100 text-slate-600"><x-icon name="book" class="h-3.5 w-3.5" /> {{ $s->class_subjects_count }} subjects</span>
                            </div>
                            <div class="mt-4"><div class="mb-1 flex justify-between text-xs text-slate-500"><span>Students</span><span>{{ $s->enrollments_count }} / {{ $s->capacity }}</span></div>
                                <div class="h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-rose-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div></div></div>
                        </a>
                    @endforeach
                </div>
            @empty
                <div class="card"><x-empty icon="layers" title="No sections{{ $level ? ' for this grade' : '' }} yet" text="Create a section, then assign teachers, a room and students."><button @click="drawer=true" class="btn btn-primary btn-sm">New section</button></x-empty></div>
            @endforelse

            {{-- New section drawer --}}
            <div x-show="drawer" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true">
                <div x-show="drawer" x-transition.opacity @click="drawer=false" class="absolute inset-0 bg-slate-900/40"></div>
                <div x-show="drawer" x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="translate-x-full"
                     class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900">
                    <div class="flex items-center border-b border-slate-100 px-6 py-4"><h2 class="flex-1 text-lg font-semibold">New section</h2><button @click="drawer=false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><x-icon name="x" /></button></div>
                    <form method="post" action="{{ route('sections.store') }}" class="flex-1 space-y-5 overflow-y-auto p-6">
                        @csrf
                        <input type="hidden" name="school_year_id" value="{{ $sy->id }}">
                        <x-field name="grade_level_id" label="Grade level" type="select" :options="$levels" :value="$level" required />
                        <x-field name="name" label="Section name" placeholder="e.g. Sampaguita" required />
                        <x-field name="adviser_id" label="Adviser" type="select" :options="$teachers" />
                        <div x-data="{ caps: @js($capacities) }">
                            <x-field name="room" label="Room" type="select" :options="$rooms" x-on:change="if (caps[$event.target.value]) $refs.cap.value = caps[$event.target.value]" />
                            @if(empty($rooms))<p class="mt-1 text-xs text-amber-700">No rooms yet – <a class="link" href="{{ route('crud.create', 'rooms') }}">add rooms</a> first.</p>@endif
                            <div class="mt-5"><label class="label" for="cap">Capacity</label><input x-ref="cap" id="cap" type="number" name="capacity" value="{{ old('capacity', 40) }}" class="input" required>
                                <p class="mt-1 text-xs text-slate-500">Filled in from the room's seats; you can lower it.</p>@error('capacity')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </div>
                        <x-field name="add_subjects" label="Add all subjects of this grade level" type="checkbox" :value="true" />
                        <button class="btn btn-primary w-full">Create section</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
