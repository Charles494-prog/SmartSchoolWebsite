<x-layouts.app title="Assignments">
    @include('portal._switch')
    @if($child)
        <h1 class="h1 mb-4">Assignments</h1>
        <div class="space-y-3">
        @forelse($assignments as $a)
            @php $s = $submissions[$a->id] ?? null; @endphp
            <div class="card" x-data="{ open:false }">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex-1"><div class="font-semibold">{{ $a->title }}</div><div class="muted">{{ $a->classSubject->subject->name }} · due {{ $a->due_at->format('M d, Y g:i A') }}</div></div>
                    @if($s?->score !== null)<span class="badge bg-green-100 text-green-800">Score {{ $s->score }}/{{ $a->max_score }}</span>
                    @elseif($s)<span class="badge bg-blue-100 text-blue-800">Submitted {{ $s->submitted_at->format('M d') }}</span>
                    @elseif($a->due_at->isPast())<span class="badge bg-red-100 text-red-800">Missing</span>
                    @else<span class="badge bg-amber-100 text-amber-800">To do</span>@endif
                    <button class="btn btn-sm" @click="open=!open">Details</button>
                </div>
                <div x-show="open" x-cloak class="mt-3 border-t pt-3 text-sm">
                    <p class="whitespace-pre-line">{{ $a->instructions }}</p>
                    @if($a->attachment_path)<p class="mt-2"><a class="link" href="{{ route('assignments.attachment', $a) }}"><x-icon name="clip" class="inline h-4 w-4" /> Download attachment</a></p>@endif
                    @if($s?->feedback)<p class="mt-2 rounded bg-green-50 p-2"><b>Teacher feedback:</b> {{ $s->feedback }}</p>@endif
                    @if(auth()->user()->role === 'student' && $s?->score === null)
                        <form method="post" action="{{ route('portal.submit', $a) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                            @csrf
                            <x-field name="body" label="Your answer / notes" type="textarea" :value="$s?->body" />
                            <x-field name="file" label="Attach file (max 10 MB)" type="file" />
                            <button class="btn btn-primary">{{ $s ? 'Resubmit' : 'Submit' }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="card muted">No assignments.</p>
        @endforelse
        </div>
    @endif
</x-layouts.app>
