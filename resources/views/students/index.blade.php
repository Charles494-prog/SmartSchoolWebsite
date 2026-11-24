<x-layouts.app title="Students">
    <x-page-header title="Students" :subtitle="$students->total().' students'">@if($canEdit)<a href="{{ route('students.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add student</a>@endif</x-page-header>
    <x-filters search="Name, student no. or LRN…" :filters="[
        'grade_level_id' => ['label' => 'Grade level', 'options' => $levels->all()],
        'section_id' => ['label' => 'Section', 'options' => $sections->all()],
        'status' => ['label' => 'Status', 'options' => ['active' => 'Active', 'transferred' => 'Transferred', 'graduated' => 'Graduated', 'dropped' => 'Dropped']],
        'sex' => ['label' => 'Sex', 'options' => ['Male' => 'Male', 'Female' => 'Female']],
    ]" />
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Student</th><th>School ID</th><th>LRN</th><th>Grade & section</th><th>Sex</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($students as $s)
                @php $e = $s->enrollments->first(); @endphp
                <tr>
                    <td><a class="link" href="{{ route('students.show', $s) }}">{{ $s->full_name }}</a></td>
                    <td>{{ $s->student_no }}</td><td>{{ $s->lrn ?: '—' }}</td>
                    <td>{{ $e ? $e->gradeLevel->name.($e->section ? ' – '.$e->section->name : ' (no section)') : 'Not enrolled this S.Y.' }}</td>
                    <td>{{ $s->sex }}</td><td><x-status :value="$s->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="users" title="No students match" text="Try removing a filter or searching another name." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $students->links() }}</div>
</x-layouts.app>
