<x-layouts.app :title="$a->title">
    <a href="{{ route('assignments.class', $a->class_subject_id) }}" class="link"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />{{ $a->classSubject->subject->name }} · {{ $a->classSubject->section->name }}</a>
    <div class="card mt-3">
        <h1 class="h1">{{ $a->title }}</h1>
        <p class="muted"><span class="font-semibold capitalize">{{ $a->type }}</span> · {{ $a->classSubject->subject->name }} · {{ $a->classSubject->section->label() }} @if($a->due_at) · due {{ $a->due_at->format('M d, Y g:i A') }} · out of {{ rtrim(rtrim(number_format((float) $a->max_score, 2), '0'), '.') }}@endif</p>
        <p class="mt-1 text-sm font-medium text-brand-700 dark:text-brand-300">{{ \App\Support\Classwork::countsToward($a) }}</p>
        <p class="mt-2 whitespace-pre-line text-sm">{{ $a->instructions }}</p>
    </div>
    <div class="card mt-4 overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Student</th><th>Status</th><th>Work</th><th>Score & feedback</th></tr></thead>
            <tbody>
            @foreach($students as $s)
                @php $sub = $subs[$s->id] ?? null; @endphp
                <tr>
                    <td>{{ $s->full_name }}</td>
                    <td>@if($sub)<span class="badge {{ $a->due_at && $sub->submitted_at > $a->due_at ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">{{ $a->due_at && $sub->submitted_at > $a->due_at ? 'Late' : 'On time' }}</span>@else<span class="badge bg-slate-100">Missing</span>@endif</td>
                    <td class="max-w-sm text-sm">{{ \Illuminate\Support\Str::limit($sub?->body, 200) }} @if($sub?->file_path)<a class="link" href="{{ route('assignments.file', $sub) }}"><x-icon name="clip" class="inline h-4 w-4" /> file</a>@endif</td>
                    <td>@if($sub)
                        <form method="post" action="{{ route('assignments.grade', $sub) }}" class="flex gap-1">@csrf
                            <input name="score" value="{{ $sub->score }}" class="input w-20 py-1" placeholder="Score"><input name="feedback" value="{{ $sub->feedback }}" class="input py-1" placeholder="Feedback"><button class="btn btn-sm">Save</button>
                        </form>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
