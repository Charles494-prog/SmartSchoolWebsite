@php
    $initials = collect(explode(' ', $u->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $tab = $errors->hasAny(['current_password', 'password']) ? 'security' : 'profile';
@endphp
<x-layouts.app title="My profile">
    <div class="mx-auto max-w-5xl" x-data="{ tab: '{{ $tab }}', preview: @js($u->avatar_path ? route('profile.photo', $u) : null), remove: false }">
        {{-- Header --}}
        <div class="card overflow-hidden p-0">
            <div class="h-28 bg-slate-100 sm:h-36 dark:bg-white/5"></div>
            <div class="flex flex-col gap-4 px-6 pb-6 sm:flex-row sm:items-end">
                <div class="relative -mt-14 h-28 w-28 shrink-0">
                    <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-3xl font-semibold text-brand-700 ring-4 ring-white dark:ring-slate-900">
                        <template x-if="preview && !remove"><img :src="preview" class="h-full w-full object-cover" alt="Your photo"></template>
                        <template x-if="!preview || remove"><span>{{ $initials }}</span></template>
                    </div>
                    <label for="photo" class="absolute right-0 bottom-0 flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-white text-slate-700 shadow ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:ring-white/10" title="Change photo">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                        <span class="sr-only">Change photo</span>
                    </label>
                </div>
                <div class="min-w-0 flex-1 sm:pb-1">
                    <h1 class="truncate text-2xl font-semibold text-slate-900">{{ $u->name }}</h1>
                    <p class="text-sm text-slate-500">{{ $u->roleLabel() }} · {{ $u->email }}</p>
                    @if($u->bio)<p class="mt-2 max-w-2xl text-sm text-slate-700 dark:text-slate-300">{{ $u->bio }}</p>@endif
                </div>
            </div>
            <div class="flex gap-1 border-t border-slate-100 px-4 dark:border-white/10" role="tablist">
                @foreach(['profile' => 'Profile', 'security' => 'Password & security'] as $k => $label)
                    <button type="button" role="tab" class="-mb-px min-h-12 border-b-2 px-4 text-sm font-medium" :class="tab === '{{ $k }}' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-800'" x-on:click="tab = '{{ $k }}'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        {{-- Profile --}}
        <form x-show="tab === 'profile'" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 lg:grid-cols-[16rem_1fr]">
            @csrf @method('put')
            <div>
                <h2 class="text-base font-semibold text-slate-900">Public profile</h2>
                <p class="mt-1 text-sm text-slate-500">Staff, parents and students see your name, photo and bio in messages and announcements.</p>
            </div>
            <div class="card space-y-5">
                <input id="photo" type="file" name="photo" accept="image/*" class="sr-only" x-on:change="preview = URL.createObjectURL($event.target.files[0]); remove = false">
                <input type="hidden" name="remove_photo" :value="remove ? 1 : 0">
                <div class="flex flex-wrap items-center gap-3">
                    <label for="photo" class="btn cursor-pointer">Upload new photo</label>
                    <button type="button" class="btn" x-show="preview && !remove" x-on:click="remove = true">Remove</button>
                    <span class="text-xs text-slate-500">Square works best. JPG or PNG, up to 4 MB.</span>
                    @error('photo')<p class="field-error w-full">{{ $message }}</p>@enderror
                </div>
                <x-field name="name" label="Full name" :value="$u->name" required />
                <div x-data="{ n: {{ mb_strlen((string) old('bio', $u->bio)) }} }">
                    <div class="flex items-baseline justify-between"><label class="label" for="bio">Bio</label><span class="text-xs text-slate-400"><span x-text="n"></span>/500</span></div>
                    <textarea id="bio" name="bio" rows="4" maxlength="500" class="input" x-on:input="n = $event.target.value.length" placeholder="For example: Grade 7 adviser. Teaches Science and Math.">{{ old('bio', $u->bio) }}</textarea>
                    @error('bio')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2 dark:bg-white/5">
                    <div><div class="text-xs text-slate-500">Login email</div><div class="font-medium">{{ $u->email }}</div></div>
                    <div><div class="text-xs text-slate-500">Role</div><div class="font-medium">{{ $u->roleLabel() }}</div></div>
                    <p class="text-xs text-slate-500 sm:col-span-2">To change your email or role, ask the school administrator.</p>
                </div>
                <div class="flex justify-end border-t border-slate-100 pt-5 dark:border-white/10"><button class="btn btn-primary">Save changes</button></div>
            </div>
        </form>

        {{-- Security --}}
        <form x-show="tab === 'security'" x-cloak method="post" action="{{ route('profile.password') }}" class="mt-6 grid gap-6 lg:grid-cols-[16rem_1fr]">
            @csrf @method('put')
            <div>
                <h2 class="text-base font-semibold text-slate-900">Change password</h2>
                <p class="mt-1 text-sm text-slate-500">Use at least 10 characters with letters and numbers. Don't reuse a password from another website.</p>
            </div>
            <div class="card space-y-5">
                <x-field name="current_password" label="Current password" type="password" required autocomplete="current-password" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="password" label="New password" type="password" required autocomplete="new-password" />
                    <x-field name="password_confirmation" label="Repeat new password" type="password" required autocomplete="new-password" />
                </div>
                <div class="flex justify-end border-t border-slate-100 pt-5 dark:border-white/10"><button class="btn btn-primary">Update password</button></div>
            </div>
        </form>
    </div>
</x-layouts.app>
