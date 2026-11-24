@php
    $sec = 'grid gap-6 border-t border-slate-200 py-10 md:grid-cols-3';
@endphp
<x-layouts.guest title="Apply for admission">
    <div class="max-w-2xl">
        <p class="text-sm font-medium text-slate-500">Admission {{ $year ? '· School year '.$year->name : '' }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Apply for admission</h1>
        <p class="mt-3 text-slate-600">It takes about 5 minutes. After you submit, you'll get a reference number to check the status. Documents can be uploaded now or brought to the registrar.</p>
    </div>

    @if(! $year)
        <p class="mt-8 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">Admission is not open yet. Please check again later.</p>
    @else
    @if($errors->any())<div class="mt-8 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Please check the fields marked in red.</div>@endif

    <form method="post" action="{{ route('apply.store') }}" enctype="multipart/form-data" class="mt-10">
        @csrf
        <section class="{{ $sec }}">
            <div><h2 class="font-semibold text-slate-900">Student</h2><p class="mt-1 text-sm text-slate-500">As written on the birth certificate.</p></div>
            <div class="grid gap-5 sm:grid-cols-2 md:col-span-2">
                <x-field name="first_name" label="First name" required />
                <x-field name="last_name" label="Last name" required />
                <x-field name="middle_name" label="Middle name" />
                <x-field name="suffix" label="Suffix" placeholder="Jr., III" />
                <x-field name="sex" label="Sex" type="select" :options="['Male' => 'Male', 'Female' => 'Female']" required />
                <x-field name="birthdate" label="Date of birth" type="date" required />
                <x-field name="birthplace" label="Place of birth" class="sm:col-span-2" />
                <x-field name="address" label="Home address" required class="sm:col-span-2" />
            </div>
        </section>

        <section class="{{ $sec }}" x-data="{ type: '{{ old('applicant_type', 'new') }}' }">
            <div><h2 class="font-semibold text-slate-900">Enrollment</h2><p class="mt-1 text-sm text-slate-500">The grade level for the coming school year.</p></div>
            <div class="grid gap-5 sm:grid-cols-2 md:col-span-2">
                <x-field name="grade_level_id" label="Grade level" type="select" :options="$levels" required />
                <div>
                    <label class="label" for="f_applicant_type">Applying as<span class="text-red-600"> *</span></label>
                    <select id="f_applicant_type" name="applicant_type" x-model="type" class="input">
                        <option value="new">New student</option><option value="transferee">Transferee</option><option value="returnee">Returning student</option>
                    </select>
                </div>
                <div x-show="type === 'transferee'" x-cloak class="sm:col-span-2"><x-field name="previous_school" label="Previous school" required /></div>
            </div>
        </section>

        <section class="{{ $sec }}">
            <div><h2 class="font-semibold text-slate-900">Parent or guardian</h2><p class="mt-1 text-sm text-slate-500">We'll send the parent portal login to this email.</p></div>
            <div class="grid gap-5 sm:grid-cols-2 md:col-span-2">
                <x-field name="guardian_name" label="Full name" required />
                <x-field name="guardian_relationship" label="Relationship" type="select" :options="['Mother' => 'Mother', 'Father' => 'Father', 'Guardian' => 'Guardian', 'Grandparent' => 'Grandparent', 'Sibling' => 'Sibling', 'Other' => 'Other']" required />
                <x-field name="guardian_phone" label="Mobile number" placeholder="09171234567" required />
                <x-field name="guardian_email" label="Email" type="email" required />
            </div>
        </section>

        <section class="{{ $sec }}">
            <div><h2 class="font-semibold text-slate-900">Documents</h2><p class="mt-1 text-sm text-slate-500">Optional now. PDF, JPG or PNG, up to 5 MB each.</p></div>
            <div class="divide-y divide-slate-200 rounded-lg border border-slate-200 md:col-span-2">
                @foreach(\App\Models\ApplicationDocument::TYPES as $k => $label)
                    <label class="flex cursor-pointer items-center gap-4 px-4 py-3 hover:bg-slate-50" x-data="{ name: '' }">
                        <x-icon name="file" class="h-5 w-5 shrink-0 text-slate-400" />
                        <span class="flex-1 text-sm"><span class="font-medium text-slate-800">{{ $label }}</span><span class="block truncate text-xs text-slate-500" x-text="name || 'No file selected'"></span></span>
                        <span class="text-sm font-medium text-slate-700 underline-offset-2 hover:underline">Choose file</span>
                        <input type="file" name="documents[{{ $k }}]" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" @change="name = $event.target.files[0]?.name || ''">
                    </label>
                    @error('documents.'.$k)<p class="field-error px-4 pb-3">{{ $message }}</p>@enderror
                @endforeach
            </div>
        </section>

        <section class="{{ $sec }}">
            <div><h2 class="font-semibold text-slate-900">Privacy</h2></div>
            <div class="md:col-span-2">
                <p class="text-sm leading-relaxed text-slate-600">{{ \App\Models\Setting::get('privacy_notice') }}</p>
                <label class="mt-4 flex items-start gap-3 text-sm"><input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('consent'))> <span>I am the parent or guardian (or the student, if of legal age) and I agree to the collection and use of this information.</span></label>
                @error('consent')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <div class="flex items-center justify-end gap-4 border-t border-slate-200 pt-8">
            <a href="{{ route('login') }}" class="text-sm text-slate-500 hover:text-slate-900">Cancel</a>
            <button class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Submit application</button>
        </div>
    </form>
    @endif
</x-layouts.guest>
