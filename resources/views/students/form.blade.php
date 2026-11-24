<x-layouts.app :title="$student ? 'Edit student' : 'Add student'">
    <a href="{{ $student ? route('students.show', $student) : route('students.index') }}" class="link"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />Back</a>
    <div class="card mt-3">
        <h1 class="h2 mb-4">{{ $student ? 'Edit '.$student->full_name : 'Add student' }}</h1>
        <form method="post" action="{{ $student ? route('students.update', $student) : route('students.store') }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div class="flex items-center gap-4 sm:col-span-2 lg:col-span-4" x-data="{ preview: @js($student?->photo_path ? route('students.photo', $student) : null) }">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"><template x-if="preview"><img :src="preview" class="h-full w-full object-cover" alt=""></template><template x-if="!preview"><x-icon name="user" class="h-10 w-10 text-slate-300" /></template></div>
                <div><label class="btn btn-sm cursor-pointer">Upload 1×1 photo<input type="file" name="photo" accept="image/*" class="sr-only" @change="preview = URL.createObjectURL($event.target.files[0])"></label>
                <p class="mt-1 text-xs text-slate-500">JPG or PNG, white background. It's cropped to a square and printed on the ID card.</p>@error('photo')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div> @if($student) @method('put') @endif
            <x-field name="last_name" label="Last name" :value="$student?->last_name" required />
            <x-field name="first_name" label="First name" :value="$student?->first_name" required />
            <x-field name="middle_name" label="Middle name" :value="$student?->middle_name" />
            <x-field name="suffix" label="Suffix" :value="$student?->suffix" />
            @if($student)<div><div class="label">School ID no.</div><div class="rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm font-semibold ring-1 ring-slate-200">{{ $student->student_no }}</div><p class="mt-1 text-xs text-slate-500">Made automatically. Used inside the school only.</p></div>@endif
            <x-field name="lrn" label="LRN (from DepEd, 12 digits)" :value="$student?->lrn" inputmode="numeric" maxlength="12" help="Entered by school staff. Leave empty if the student has none yet." />
            <x-field name="sex" label="Sex" type="select" :options="['Male' => 'Male', 'Female' => 'Female']" :value="$student?->sex" required />
            <x-field name="birthdate" label="Birthdate" type="date" :value="$student?->birthdate?->format('Y-m-d')" required />
            <x-field name="birthplace" label="Birthplace" :value="$student?->birthplace" />
            <x-field name="address" label="Address" :value="$student?->address" class="sm:col-span-2" />
            <x-field name="religion" label="Religion" :value="$student?->religion" />
            <x-field name="nationality" label="Nationality" :value="$student?->nationality ?? 'Filipino'" />
            <x-field name="phone" label="Student mobile no." :value="$student?->phone" />
            <x-field name="email" label="Student email" type="email" :value="$student?->email" />
            @if($student)
                <x-field name="status" label="Status" type="select" :options="['active' => 'Active', 'transferred' => 'Transferred', 'graduated' => 'Graduated', 'dropped' => 'Dropped']" :value="$student->status" required />
            @else
                <x-field name="grade_level_id" label="Enroll in grade level (current S.Y.)" type="select" :options="$levels" />
                <x-field name="section_id" label="Section" type="select" :options="$sections" />
            @endif
            <x-field name="medical_notes" label="Medical notes (allergies, conditions, medicines)" type="textarea" :value="$student?->medical_notes" class="sm:col-span-2 lg:col-span-4" />
            <div class="sm:col-span-2 lg:col-span-4"><button class="btn btn-primary">Save</button></div>
        </form>
    </div>
</x-layouts.app>
