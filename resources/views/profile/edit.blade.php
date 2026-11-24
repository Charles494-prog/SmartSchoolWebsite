<x-layouts.app title="My profile">
    <x-page-header title="My profile" subtitle="How you appear to others in SmartSchool." />
    <div class="grid max-w-3xl gap-6">
        <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card space-y-5" x-data="{ preview: @js($u->avatar_path ? route('profile.photo', $u) : null), remove: false }">
            @csrf @method('put')
            <div class="flex flex-wrap items-center gap-5">
                <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200">
                    <template x-if="preview && !remove"><img :src="preview" class="h-full w-full object-cover" alt="Your photo"></template>
                    <template x-if="!preview || remove"><x-icon name="user" class="h-10 w-10 text-slate-400" /></template>
                </div>
                <div class="flex flex-wrap gap-2">
                    <label class="btn cursor-pointer">Upload photo<input type="file" name="photo" accept="image/*" class="sr-only" x-on:change="preview = URL.createObjectURL($event.target.files[0]); remove = false"></label>
                    <button type="button" class="btn" x-show="preview && !remove" x-on:click="remove = true">Remove photo</button>
                    <input type="hidden" name="remove_photo" :value="remove ? 1 : 0">
                </div>
                @error('photo')<p class="field-error w-full">{{ $message }}</p>@enderror
            </div>
            <x-field name="name" label="Name" :value="$u->name" required />
            <div>
                <label class="label" for="bio">Bio</label>
                <textarea id="bio" name="bio" rows="3" maxlength="500" class="input" placeholder="For example: Grade 7 adviser, teaches Science">{{ old('bio', $u->bio) }}</textarea>
                @error('bio')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="muted">Email (login)</dt><dd class="font-medium">{{ $u->email }}</dd></div>
                <div><dt class="muted">Role</dt><dd class="font-medium">{{ $u->roleLabel() }}</dd></div>
            </dl>
            <div><button class="btn btn-primary">Save profile</button></div>
        </form>

        <form method="post" action="{{ route('profile.password') }}" class="card space-y-5">
            @csrf @method('put')
            <div><h2 class="h2">Password</h2><p class="muted text-sm">At least 10 characters, with letters and numbers.</p></div>
            <x-field name="current_password" label="Current password" type="password" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="password" label="New password" type="password" required />
                <x-field name="password_confirmation" label="Type it again" type="password" required />
            </div>
            <div><button class="btn btn-primary">Change password</button></div>
        </form>
    </div>
</x-layouts.app>
