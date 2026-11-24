<x-layouts.app title="QR ID cards">
    <x-page-header title="QR ID cards" subtitle="Download, print and cut. Each card has the student's personal QR code that teachers scan for attendance." />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="mb-3 font-bold">Whole section (10 cards per A4 page)</h2>
            @if($sections->isEmpty())
                <div class="card"><x-empty icon="layers" title="No sections yet" text="Create sections first in Classes & sections."><a href="{{ route('sections.index') }}" class="btn btn-primary btn-sm">Go to Classes & sections</a></x-empty></div>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($sections as $s)
                        <div class="card flex items-center gap-4 p-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="qr" /></div>
                            <div class="flex-1"><div class="font-semibold">{{ $s->label() }}</div><div class="text-sm text-slate-500">{{ $s->enrollments_count }} students</div></div>
                            <a href="{{ route('sections.ids', $s) }}" target="_blank" class="btn btn-primary btn-sm">Download PDF</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <h2 class="mb-3 font-bold">One student (new or lost card)</h2>
            <div class="card">
                <form class="flex gap-2"><input name="q" value="{{ request('q') }}" class="input" placeholder="Name or student no."><button class="btn">Find</button></form>
                <div class="mt-4 space-y-1">
                    @foreach($students as $st)
                        <a href="{{ route('students.id', $st) }}" target="_blank" class="flex items-center justify-between rounded-xl p-2.5 text-sm hover:bg-slate-50"><span>{{ $st->full_name }} <span class="text-slate-400">{{ $st->student_no }}</span></span><span class="link">PDF</span></a>
                    @endforeach
                    @if(request('q') && $students->isEmpty())<p class="muted">No student found.</p>@endif
                </div>
            </div>
            <p class="mt-4 text-sm text-slate-500">Tip: the QR code contains a private code, not personal details. If a card is lost, simply print a new one.</p>
        </div>
    </div>
</x-layouts.app>
