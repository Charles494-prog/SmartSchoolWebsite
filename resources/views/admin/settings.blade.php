@php
    $S = fn ($k) => \App\Models\Setting::get($k);
    $sections = [
        'general' => ['School profile', 'Shown on report cards, receipts, ID cards and the public pages.', [
            ['school_name', 'School name', 'text', null],
            ['school_address', 'Address', 'text', null],
            ['school_head', 'Principal / school head', 'text', 'Printed as the signatory on report cards.'],
            ['school_email', 'Email', 'email', 'Shown to parents for privacy and other requests.'],
            ['school_phone', 'Phone', 'text', null],
        ]],
        'grading' => ['Grading', 'How grades are computed and who gets honors.', [
            ['use_transmutation', 'Transmutation table', 'toggle', 'Curve raw scores into grades. Off = plain weighted average.'],
            ['passing_grade', 'Passing grade', 'number', 'Final grades below this are marked Failed.'],
            ['honors_with_highest', 'With Highest Honors', 'number', 'Minimum general average.'],
            ['honors_with_high', 'With High Honors', 'number', 'Minimum general average.'],
            ['honors_with', 'With Honors', 'number', 'Minimum general average.'],
        ]],
        'attendance' => ['Attendance & notifications', 'Gate check-in rules and messages to parents.', [
            ['late_after', 'Late after', 'time', 'Gate scans after this time are marked late.'],
            ['notify_absent', 'Absence alerts', 'toggle', 'Notify parents (portal + email) when a student is absent.'],
            ['notify_late', 'Late alerts', 'toggle', 'Notify parents when a student arrives late.'],
        ]],
        'library' => ['Library', 'Borrowing rules.', [
            ['library_loan_days', 'Loan period', 'number', 'Days a student may keep a book.'],
            ['library_fine_per_day', 'Late fine per day (₱)', 'number', null],
        ]],
    ];
@endphp
<x-layouts.app title="School settings" :hideErrorSummary="true">
    <x-page-header title="Settings" subtitle="Manage how your school is set up. Changes apply to everyone." />

    <form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" x-data="{ dirty: false }" @input="dirty = true" @change="dirty = true">
        @csrf @method('put')
        <div class="grid gap-8 lg:grid-cols-[13rem_1fr]">
            {{-- Section nav --}}
            <nav class="hidden lg:block">
                <ul class="sticky top-24 space-y-0.5 text-sm">
                    @foreach($sections + ['privacy' => ['Privacy notice']] as $id => [$title])
                        <li><a href="#{{ $id }}" class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5">{{ $title }}</a></li>
                    @endforeach
                    <li class="pt-3"><a href="{{ route('crud.index', 'school-years') }}" class="block rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">School years →</a></li>
                    <li><a href="{{ route('crud.index', 'rooms') }}" class="block rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">Rooms →</a></li>
                    <li><a href="{{ route('crud.index', 'users') }}" class="block rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">User accounts →</a></li>
                </ul>
            </nav>

            <div class="space-y-8">
                @foreach($sections as $id => [$title, $desc, $fields])
                    <section id="{{ $id }}" class="card scroll-mt-24 p-0">
                        <div class="border-b border-slate-100 px-6 py-5 dark:border-white/10">
                            <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $desc }}</p>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-white/5">
                            @if($id === 'general')
                                <div class="grid gap-3 px-6 py-5 sm:grid-cols-[16rem_1fr] sm:items-center" x-data="{ preview: @js($S('logo_path') ? route('school.logo') : null) }">
                                    <div><div class="text-sm font-medium text-slate-900">Logo</div><div class="text-xs text-slate-500">Square PNG or JPG, up to 2 MB.</div></div>
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                            <template x-if="preview"><img :src="preview" class="h-full w-full object-contain" alt=""></template>
                                            <template x-if="!preview"><x-icon name="cap" class="h-6 w-6 text-slate-300" /></template>
                                        </div>
                                        <label class="btn btn-sm cursor-pointer">Upload new logo<input type="file" name="logo" accept="image/*" class="sr-only" @change="preview = URL.createObjectURL($event.target.files[0])"></label>
                                        @error('logo')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            @endif
                            @foreach($fields as [$k, $label, $type, $help])
                                <div class="grid gap-3 px-6 py-5 sm:grid-cols-[16rem_1fr] sm:items-center">
                                    <div>
                                        <label for="f_{{ $k }}" class="text-sm font-medium text-slate-900">{{ $label }}</label>
                                        @if($help)<p class="text-xs text-slate-500">{{ $help }}</p>@endif
                                    </div>
                                    <div>
                                        @if($type === 'toggle')
                                            <div x-data="{ on: @js(old($k, $S($k)) === '1' || old($k) === true) }" class="flex items-center gap-3">
                                                <input type="hidden" name="{{ $k }}" :value="on ? 1 : 0">
                                                <button type="button" role="switch" :aria-checked="on" @click="on=!on; dirty=true" aria-label="{{ $label }}" :class="on ? 'bg-brand-600' : 'bg-slate-300'" class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition">
                                                    <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition"></span>
                                                </button>
                                                <span class="text-sm text-slate-500" x-text="on ? 'On' : 'Off'"></span>
                                            </div>
                                        @else
                                            <input id="f_{{ $k }}" type="{{ $type }}" name="{{ $k }}" value="{{ old($k, $S($k)) }}" @if($type === 'number') step="any" @endif
                                                   class="input {{ in_array($type, ['number', 'time']) ? 'max-w-40' : 'max-w-lg' }} @error($k) ring-rose-500 @enderror">
                                            @error($k)<p class="field-error">{{ $message }}</p>@enderror
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <section id="privacy" class="card scroll-mt-24 p-0">
                    <div class="border-b border-slate-100 px-6 py-5 dark:border-white/10">
                        <h2 class="text-base font-semibold text-slate-900">Privacy notice</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Shown on the application form and the privacy page (Data Privacy Act of 2012).</p>
                    </div>
                    <div class="px-6 py-5">
                        <textarea name="privacy_notice" rows="6" class="input">{{ old('privacy_notice', $S('privacy_notice')) }}</textarea>
                        @error('privacy_notice')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </section>
            </div>
        </div>

        {{-- Save bar --}}
        <div class="sticky bottom-0 z-20 mt-8 -mx-4 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 dark:border-white/10 dark:bg-slate-900/90">
            <div class="flex items-center justify-end gap-3">
                <span class="text-sm text-slate-500" x-show="dirty" x-cloak>You have unsaved changes</span>
                <a href="{{ route('settings.edit') }}" class="btn" x-show="dirty" x-cloak>Discard</a>
                <button class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </form>
</x-layouts.app>
