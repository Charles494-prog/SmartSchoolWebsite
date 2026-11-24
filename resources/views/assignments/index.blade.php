<x-layouts.app title="Assignments">
    <h1 class="h1 mb-4">Assignments</h1>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card overflow-x-auto p-0 lg:col-span-2">
            <table class="table">
                <thead><tr><th>Title</th><th>Class</th><th>Due</th><th>Submitted</th></tr></thead>
                <tbody>
                @forelse($items as $a)
                    <tr><td><a class="link" href="{{ route('assignments.show', $a) }}">{{ $a->title }}</a></td><td>{{ $a->classSubject->subject->name }} · {{ $a->classSubject->section->name }}</td><td>{{ $a->due_at->format('M d, g:i A') }}</td><td>{{ $a->submissions_count }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">No assignments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card h-fit">
            <h2 class="h2 mb-3">New assignment</h2>
            <form method="post" action="{{ route('assignments.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <x-field name="class_subject_id" label="Class" type="select" :options="$classes" required />
                <x-field name="title" label="Title" required />
                <x-field name="instructions" label="Instructions" type="textarea" />
                <x-field name="due_at" label="Due" type="datetime-local" :value="now()->addWeek()->setTime(17, 0)->format('Y-m-d\TH:i')" required />
                <x-field name="max_score" label="Highest score" type="number" value="100" required />
                <x-field name="attachment" label="Attachment (optional)" type="file" />
                <button class="btn btn-primary w-full">Post to students</button>
            </form>
        </div>
    </div>
</x-layouts.app>
