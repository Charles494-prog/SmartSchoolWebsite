<x-layouts.app title="Take attendance">
    <x-page-header title="Take attendance" subtitle="Two quick steps. Parents of late or absent students are notified automatically." />

    {{-- Step indicator --}}
    <ol class="mb-8 flex items-center gap-3 text-sm font-medium">
        @foreach(['Choose the class', 'Choose how', 'Done'] as $i => $label)
            @php $state = ! $section ? ($i === 0 ? 'now' : 'next') : ($i === 0 ? 'done' : ($i === 1 ? 'now' : 'next')); @endphp
            <li class="flex items-center gap-2 {{ $state === 'next' ? 'text-slate-400' : 'text-slate-900' }}">
                <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $state === 'done' ? 'bg-emerald-500 text-white' : ($state === 'now' ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500') }}">@if($state === 'done')<x-icon name="check" class="h-4 w-4" />@else{{ $i + 1 }}@endif</span>
                {{ $label }}
            </li>
            @if($i < 2)<li class="h-px w-8 bg-slate-200 sm:w-16"></li>@endif
        @endforeach
    </ol>

    @if(! $section)
        <h2 class="mb-3 text-lg font-bold">1. Which class?</h2>
        @if($sections->isEmpty())
            <div class="card"><x-empty icon="layers" title="No classes yet" text="You'll see your classes here once the registrar assigns you to a section or subject." /></div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($sections as $s)
                    <a href="?section={{ $s->id }}" class="card group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-brand-200">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white"><x-icon name="users" /></div>
                        <div class="flex-1"><div class="font-semibold">{{ $s->label() }}</div><div class="text-sm text-slate-500">{{ $s->enrollments_count }} students{{ $s->adviser_id === auth()->id() ? ' · your advisory' : '' }}</div></div>
                        <x-icon name="chevron" class="h-5 w-5 text-slate-300 group-hover:text-brand-500" />
                    </a>
                @endforeach
            </div>
        @endif
    @else
        <div class="mb-4 flex items-center gap-3">
            <h2 class="text-lg font-bold">2. How do you want to take attendance for {{ $section->label() }}?</h2>
            <a href="{{ route('attendance.start') }}" class="link text-sm">Change class</a>
        </div>
        @if($done)<p class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $done }} of {{ $section->students()->count() }} students already have attendance today.</p>@endif
        <div class="grid gap-4 md:grid-cols-2">
            <a href="{{ route('attendance.scan') }}" class="card group p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-brand-200">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 group-hover:bg-brand-600 group-hover:text-white"><x-icon name="qr" class="h-7 w-7" /></div>
                <div class="text-lg font-bold">Scan QR ID cards</div>
                <p class="mt-1 text-sm text-slate-500">Best at the door or gate. Students show their card; use a USB scanner or this device's camera. Arrival time is saved and lateness is detected automatically.</p>
                <span class="btn btn-primary mt-5">Open the scanner</span>
            </a>
            <a href="{{ route('attendance.sheet', ['section_id' => $section->id]) }}" class="card group p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-brand-200">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white"><x-icon name="check-square" class="h-7 w-7" /></div>
                <div class="text-lg font-bold">Tick a class list</div>
                <p class="mt-1 text-sm text-slate-500">Best inside the classroom or when someone forgot their card. Mark everyone present in one click, then change the few who are late or absent.</p>
                <span class="btn mt-5">Open the class list</span>
            </a>
        </div>
        <p class="mt-6 text-sm text-slate-500">3. That's it – records are saved as you go. See totals any time in <a class="link" href="{{ route('attendance.report', ['section_id' => $section->id]) }}">Attendance report</a>.</p>
    @endif
</x-layouts.app>
