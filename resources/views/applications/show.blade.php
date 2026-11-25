<x-layouts.app :title="'Application '.$app->reference_no">
    <a href="{{ route('applications.index') }}" class="link"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />Applications</a>
    <div class="mt-3 grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between"><h1 class="h1">{{ $app->full_name }}</h1><x-status :value="$app->status" /></div>
            <p class="muted">{{ $app->reference_no }} · {{ $app->gradeLevel->name }} · S.Y. {{ $app->schoolYear->name }} · {{ ucfirst($app->applicant_type) }}</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                @foreach(['LRN' => $app->lrn, 'Sex' => $app->sex, 'Birthdate' => $app->birthdate->format('M d, Y').' ('.$app->birthdate->age.' yrs)', 'Birthplace' => $app->birthplace, 'Address' => $app->address, 'Previous school' => $app->previous_school, 'Guardian' => $app->guardian_name.' ('.$app->guardian_relationship.')', 'Guardian mobile' => $app->guardian_phone, 'Guardian email' => $app->guardian_email, 'Privacy consent' => $app->consent_at->format('M d, Y g:i A')] as $k => $v)
                    <div><dt class="muted">{{ $k }}</dt><dd class="font-medium">{{ $v ?: '—' }}</dd></div>
                @endforeach
            </dl>
            <h2 class="h2 mt-6 mb-2">Documents</h2>
            <ul class="space-y-1 text-sm">
                @forelse($app->documents as $d)
                    <li class="flex items-center gap-2"><x-icon name="clip" class="h-4 w-4 text-slate-400" /> <a class="link" href="{{ route('applications.document', $d) }}">{{ \App\Support\Admission::label($d->type) }}</a> <span class="muted">{{ $d->original_name }}</span></li>
                @empty
                    <li class="muted">No documents uploaded. Ask the family to bring them.</li>
                @endforelse
            </ul>
            @if($app->remarks)<p class="mt-4 text-sm"><b>Remarks:</b> {{ $app->remarks }}</p>@endif
        </div>
        <div class="card h-fit">
            @if($app->status === 'approved')
                <p class="text-sm">Approved {{ $app->reviewed_at?->format('M d, Y') }} by {{ $app->reviewer?->name }}.</p>
                @if($app->student_id)<a href="{{ route('students.show', $app->student_id) }}" class="btn btn-primary mt-3">Open student profile</a>@endif
            @else
                <h2 class="h2 mb-3">Decision</h2>
                <form method="post" action="{{ route('applications.decide', $app) }}" class="space-y-3" x-data="{ d: 'approve' }">
                    @csrf
                    <div class="space-y-1 text-sm">
                        <label class="flex gap-2"><input type="radio" name="decision" value="approve" x-model="d"> Approve & enroll</label>
                        <label class="flex gap-2"><input type="radio" name="decision" value="for_review" x-model="d"> Mark "for review" (needs documents)</label>
                        <label class="flex gap-2"><input type="radio" name="decision" value="reject" x-model="d"> Reject</label>
                    </div>
                    <div x-show="d==='approve'">
                        <x-field name="section_id" label="Section (optional, can be set later)" type="select" :options="$sections->mapWithKeys(fn($s) => [$s->id => $s->name.' ('.$s->enrollments_count.'/'.$s->capacity.')'])" />
                        <p class="mt-2 text-xs text-slate-500">Creates the student record, a parent portal account and the enrollment.</p>
                    </div>
                    <x-field name="remarks" label="Remarks / reason" type="textarea" />
                    <button class="btn btn-primary w-full">Save decision</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
