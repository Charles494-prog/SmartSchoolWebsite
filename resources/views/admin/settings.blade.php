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
                    @foreach($sections + ['tuition' => ['Tuition'], 'mode' => ['How SmartSchool runs'], 'email' => ['Email (SMTP)'], 'security' => ['Sign-in security'], 'admission' => ['Admission documents'], 'privacy' => ['Privacy notice']] as $id => [$title])
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

                @php
                    $tAmounts = $tuition; $tVals = array_values(array_filter($tAmounts));
                    $tAll = old('tuition_all', $tVals ? max($tVals) : '');
                @endphp
                <section id="tuition" class="card scroll-mt-24 p-0" x-data="{ type: @js(old('school_type', $S('school_type'))), mode: @js(old('tuition_mode', $S('tuition_mode'))) }">
                    <div class="border-b border-slate-100 px-6 py-5 dark:border-white/10">
                        <h2 class="text-base font-semibold text-slate-900">Tuition</h2>
                        <p class="mt-0.5 text-sm text-slate-500">For {{ $year?->name ? 'S.Y. '.$year->name : 'the current school year' }}. Other fees (miscellaneous, ID, books) are on the <a class="link" href="{{ route('crud.index', 'fee-items') }}">Fees</a> page.</p>
                    </div>
                    <div class="space-y-5 px-6 py-5">
                        <fieldset>
                            <legend class="text-sm font-medium text-slate-900">What kind of school is this?</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                @foreach(['public' => ['Public school', 'No tuition. Students pay ₱0.'], 'private' => ['Private school', 'Students pay tuition.']] as $v => [$t, $d])
                                    <label class="flex min-h-11 cursor-pointer gap-3 rounded-xl border p-4" :class="type === '{{ $v }}' ? 'border-brand-600 ring-1 ring-brand-600' : 'border-slate-200'">
                                        <input type="radio" name="school_type" value="{{ $v }}" x-model="type" class="mt-1">
                                        <span><span class="block text-sm font-semibold">{{ $t }}</span><span class="block text-xs text-slate-500">{{ $d }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div x-show="type === 'private'" x-cloak class="space-y-5">
                            <fieldset>
                                <legend class="text-sm font-medium text-slate-900">How much is tuition?</legend>
                                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                    @foreach(['same' => ['Same for every grade level', 'One amount for everyone.'], 'per_level' => ['Different per grade level', 'Set an amount for each level.']] as $v => [$t, $d])
                                        <label class="flex min-h-11 cursor-pointer gap-3 rounded-xl border p-4" :class="mode === '{{ $v }}' ? 'border-brand-600 ring-1 ring-brand-600' : 'border-slate-200'">
                                            <input type="radio" name="tuition_mode" value="{{ $v }}" x-model="mode" class="mt-1">
                                            <span><span class="block text-sm font-semibold">{{ $t }}</span><span class="block text-xs text-slate-500">{{ $d }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div x-show="mode === 'same'">
                                <label for="tuition_all" class="text-sm font-medium text-slate-900">Tuition per student for the school year (₱)</label>
                                <input id="tuition_all" type="number" step="0.01" min="0" name="tuition_all" value="{{ $tAll }}" class="input mt-1 max-w-48" :disabled="mode !== 'same' || type !== 'private'">
                                @error('tuition_all')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="mode === 'per_level'" class="overflow-hidden rounded-xl border border-slate-200">
                                <table class="table">
                                    <thead><tr><th>Grade level</th><th>Tuition for the school year (₱)</th></tr></thead>
                                    <tbody>
                                    @forelse($levels as $id => $name)
                                        <tr><td class="font-medium">{{ $name }}</td><td><input type="number" step="0.01" min="0" name="tuition[{{ $id }}]" value="{{ old('tuition.'.$id, $tAmounts[$id] ?? '') }}" class="input max-w-48" aria-label="Tuition for {{ $name }}" :disabled="mode !== 'per_level' || type !== 'private'"></td></tr>
                                    @empty
                                        <tr><td colspan="2" class="muted">Add grade levels first (School setup → Grade levels).</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">Changing tuition updates every student's balance for this school year right away.</p>
                    </div>
                </section>

                <section id="mode" class="card scroll-mt-24 p-0" x-data="{ mode: @js(\App\Support\School::mode()) }">
                    <div class="border-b border-slate-100 px-6 py-5 dark:border-white/10">
                        <h2 class="text-base font-semibold text-slate-900">How SmartSchool runs</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Chosen in the installer. Change it when your school moves from the school Wi-Fi to the internet, or back. <a class="link" href="{{ route('help.go-online') }}">How to put SmartSchool online →</a></p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach(['lan' => ['Offline on school Wi-Fi (LAN)', 'Works inside the school network. Staff register students; no student or parent accounts.'], 'hosted' => ['Online (web host)', 'Reachable from anywhere. Student and parent accounts, online applications and email notices.']] as $v => [$t, $d])
                                <label class="flex min-h-11 cursor-pointer gap-3 rounded-xl border p-4" :class="mode === '{{ $v }}' ? 'border-brand-600 ring-1 ring-brand-600' : 'border-slate-200'">
                                    <input type="radio" name="deployment_mode" value="{{ $v }}" x-model="mode" class="mt-1">
                                    <span><span class="block text-sm font-semibold">{{ $t }}</span><span class="block text-xs text-slate-500">{{ $d }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        <div x-show="mode !== @js(\App\Support\School::mode())" x-cloak class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold" x-text="mode === 'hosted' ? 'Before switching to Online' : 'Before switching to LAN'"></p>
                            <ul class="mt-1 list-disc space-y-1 pl-5" x-show="mode === 'hosted'">
                                <li><b>SmartSchool becomes fully online: everyone needs internet to use it.</b> If the school's internet is down, nobody can sign in – including teachers taking attendance and the gate scanner.</li>
                                <li>New features turn on: parent and student accounts, the online application form, email alerts, <b>Email (SMTP)</b> settings and <b>two-step sign-in</b> (both below on this page).</li>
                                <li>Make a backup first (School setup → Backups).</li>
                                <li>This turns on the online features. To reach SmartSchool from outside the school, it must also run on a web host – install it there with the Online option and restore your backup.</li>
                            </ul>
                            <a x-show="mode === 'hosted'" href="{{ route('help.go-online') }}" class="btn btn-sm mt-3 min-h-11 bg-white"><x-icon name="sparkles" class="h-4 w-4 text-violet-500" /> Step-by-step guide to go online</a>
                            <ul class="mt-1 list-disc space-y-1 pl-5" x-show="mode === 'lan'">
                                <li>Student and parent logins stop working; their data is kept.</li>
                                <li>Online applications and email notices are turned off.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                @php $online = \App\Support\School::allows('email_notifications'); @endphp
                <section id="email" class="card scroll-mt-24 p-0">
                    <input type="hidden" name="mail_present" value="1">
                    <div class="border-b border-slate-100 px-6 py-5 dark:border-white/10">
                        <h2 class="text-base font-semibold text-slate-900">Email (SMTP)</h2>
                        <p class="mt-0.5 text-sm text-slate-500">The account SmartSchool sends email from: absence and late alerts, announcements, receipts and sign-in codes. Get these values from your email provider (Gmail with an app password, Brevo, Mailgun, your web host…).</p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        @unless($online)
                            <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-white/5">Email works only in <b>Online</b> mode. You can fill this in now – it starts working when you switch to Online.</p>
                        @endunless
                        <div class="grid gap-4 sm:grid-cols-[1fr_8rem_10rem]">
                            <x-field name="mail_host" label="SMTP server" :value="$S('mail_host')" placeholder="smtp.gmail.com" />
                            <x-field name="mail_port" label="Port" type="number" :value="$S('mail_port') ?: '587'" />
                            <x-field name="mail_encryption" label="Security" type="select" :options="['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None']" :value="$S('mail_encryption') ?: 'tls'" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="mail_username" label="Username" :value="$S('mail_username')" placeholder="usually your email address" />
                            <div>
                                <label class="label" for="mail_password">Password</label>
                                <input id="mail_password" name="mail_password" type="password" autocomplete="new-password" class="input" placeholder="{{ $S('mail_password') ? 'Saved – type to replace' : 'App password from your provider' }}">
                                <p class="mt-1 text-xs text-slate-500">Stored encrypted. Leave empty to keep the saved one.</p>
                            </div>
                            <x-field name="mail_from_address" label="Send from (email)" type="email" :value="$S('mail_from_address')" placeholder="noreply@yourschool.edu.ph" />
                            <x-field name="mail_from_name" label="Send from (name)" :value="$S('mail_from_name')" :placeholder="$S('school_name')" />
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" form="test-email" class="btn btn-sm min-h-11" @disabled(! $online || ! \App\Support\SchoolMail::configured())>Send a test email to me</button>
                            <span class="text-xs text-slate-500">Save changes first, then send a test.</span>
                        </div>
                    </div>
                </section>

                <section id="security" class="card scroll-mt-24 p-0">
                    <div class="border-b border-slate-100 px-6 py-5 dark:border-white/10">
                        <h2 class="text-base font-semibold text-slate-900">Sign-in security</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Two-step sign-in: after the password, people type a 6-digit code sent to their email. Stops someone who guessed or stole a password.</p>
                    </div>
                    <div class="space-y-3 px-6 py-5">
                        @php $twoAvailable = \App\Support\TwoStep::available(); $two = old('two_step', $S('two_step') ?: 'off'); @endphp
                        @foreach(\App\Support\TwoStep::OPTIONS as $k => $label)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl p-3 ring-1 ring-slate-200 has-[:checked]:ring-2 has-[:checked]:ring-brand-500 {{ ! $twoAvailable && $k !== 'off' ? 'cursor-not-allowed opacity-50' : '' }}">
                                <input type="radio" name="two_step" value="{{ $k }}" @checked($two === $k) @disabled(! $twoAvailable && $k !== 'off')>
                                <span class="text-sm font-medium text-slate-800">{{ $label }}</span>
                            </label>
                        @endforeach
                        @error('two_step')<p class="field-error">{{ $message }}</p>@enderror
                        @unless($twoAvailable)<p class="text-xs text-slate-500">Available after switching to <b>Online</b> and setting up <b>Email (SMTP)</b> above – otherwise nobody could receive their code.</p>@endunless
                    </div>
                </section>

                <section id="admission" class="card scroll-mt-24 p-0">
                    <div class="border-b border-slate-100 px-6 py-5 dark:border-white/10">
                        <h2 class="text-base font-semibold text-slate-900">Admission documents</h2>
                        <p class="mt-0.5 text-sm text-slate-500">What families upload with an application. Required documents must be attached before the form can be sent.</p>
                    </div>
                    <div class="px-6 py-5"><x-document-requirements /></div>
                </section>

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
    <form id="test-email" method="post" action="{{ route('settings.test-email') }}" class="hidden">@csrf</form>
</x-layouts.app>
