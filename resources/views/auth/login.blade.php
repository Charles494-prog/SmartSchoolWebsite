<x-layouts.guest title="Log in">
    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-semibold text-slate-900">Log in</h1>
        <p class="mt-2 text-sm text-slate-500">Use the email and password the school gave you.</p>
        <form method="post" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
            <x-field name="email" label="Email" type="email" required autofocus />
            <div x-data="{ show:false }">
                <label class="label" for="pw">Password</label>
                <div class="relative">
                    <input id="pw" :type="show ? 'text' : 'password'" name="password" class="input pr-16" required>
                    <button type="button" @click="show=!show" class="absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium text-slate-500 hover:text-slate-900" x-text="show ? 'Hide' : 'Show'"></button>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded border-slate-300"> Keep me logged in</label>
            <button class="w-full rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Log in</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500">Forgot your password? Ask the school office.</p>
        <div class="mt-10 border-t border-slate-200 pt-6 text-center text-sm text-slate-600">New student? <a href="{{ route('apply.create') }}" class="font-medium text-slate-900 underline underline-offset-2">Apply for admission</a></div>
    </div>
</x-layouts.guest>
