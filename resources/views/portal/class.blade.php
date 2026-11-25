<x-layouts.app :title="$child ? $class->subject->name : 'Class'">
    @include('portal._switch')
    @if($child)
        @php $CW = \App\Support\Classwork::class; $mine = $CW::colorKey($class, auth()->user()); @endphp
        <div x-data="{ filter: 'all', colors: false }">
            <a href="{{ route('portal.classes', request()->only('child')) }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />My classes</a>
            <div class="relative mt-3 rounded-2xl {{ $CW::color($class, auth()->user()) }} p-6 text-white sm:p-8">
                <h1 class="text-3xl font-bold">{{ $class->subject->name }}</h1>
                <p class="mt-1 text-white/85">{{ $class->section->label() }} · {{ $class->teacher?->name }}</p>
                <button type="button" class="absolute top-4 right-4 rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium backdrop-blur hover:bg-white/25" x-on:click="colors = !colors">Change colour</button>
                <form x-show="colors" x-cloak method="post" action="{{ route('portal.color', $class) }}" class="mt-4 flex flex-wrap items-center gap-2">@csrf
                    @foreach($CW::COLORS as $key => $grad)
                        <button name="color" value="{{ $key }}" class="h-8 w-8 rounded-full {{ $grad }} ring-2 {{ $mine === $key ? 'ring-white' : 'ring-white/30' }}" aria-label="{{ ucfirst($key) }}"></button>
                    @endforeach
                    <button name="color" value="" class="rounded-full bg-white/15 px-3 py-1 text-xs">Use teacher's colour</button>
                </form>
            </div>
            <div class="my-5 flex flex-wrap gap-2">
                @foreach(['all' => 'Everything', 'work' => 'Classwork', 'announcement' => 'Announcements', 'material' => 'Documents'] as $k => $label)
                    <button type="button" class="rounded-full border px-3 py-1 text-sm" :class="filter === '{{ $k }}' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" x-on:click="filter = '{{ $k }}'">{{ $label }}</button>
                @endforeach
            </div>
            <div class="space-y-3">
                @forelse($posts as $p)
                    @include('assignments._post', ['p' => $p, 'teacher' => false, 'sub' => $submissions[$p->id] ?? null, 'students' => 0])
                @empty
                    <p class="card muted">Your teacher hasn't posted anything yet.</p>
                @endforelse
            </div>
        </div>
    @endif
</x-layouts.app>
