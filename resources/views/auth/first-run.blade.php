<x-layouts.guest title="Set up SmartSchool">
    <div class="mx-auto max-w-md">
        <p class="text-sm font-semibold text-slate-500">First-time setup</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900">Create the Superadmin account</h1>
        <p class="mt-2 text-sm text-slate-500">This is the main account of your school. It can do everything, including creating accounts for teachers and staff.</p>
        <div class="mt-5 flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <x-icon name="alert" class="h-5 w-5 shrink-0" />
            <p><b>Write the email and password down and keep them safe.</b> Every other account can be reset by the Superadmin, but nobody can recover the Superadmin for you.</p>
        </div>
        <form method="post" action="{{ route('first-run.store') }}" class="mt-6 space-y-5">
            @csrf
            <x-field name="school_name" label="School name" required autofocus />
            <x-field name="name" label="Your full name" required />
            <x-field name="email" label="Email (used to log in)" type="email" required />
            <x-field name="password" label="Password (at least 10 characters, letters and numbers)" type="password" required />
            <x-field name="password_confirmation" label="Type the password again" type="password" required />
            <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="written_down" value="1" class="mt-0.5 rounded border-slate-300" required> I wrote down the email and password and will keep them safe.</label>
            @error('written_down')<p class="field-error">{{ $message }}</p>@enderror
            <button class="w-full rounded-lg bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700">Create Superadmin and continue</button>
        </form>
    </div>
</x-layouts.guest>
