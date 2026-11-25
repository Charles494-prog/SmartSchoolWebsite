<x-layouts.guest title="Enter your sign-in code">
    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-semibold text-slate-900">Check your email</h1>
        <p class="mt-2 text-sm text-slate-500">We sent a 6-digit code to <b class="text-slate-700">{{ $email }}</b>. It expires in 10 minutes.</p>
        @if(session('error'))<div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>@endif
        <form method="post" action="{{ route('login.verify') }}" class="mt-8 space-y-5" x-data="{ busy: false }" x-on:submit="busy = true">
            @csrf
            <div>
                <label class="label" for="code">Sign-in code</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus
                       class="input min-h-12 text-center font-mono text-2xl tracking-[0.5em]" placeholder="••••••">
                @error('code')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="min-h-11 w-full rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60" :disabled="busy" x-text="busy ? 'Checking…' : 'Continue'">Continue</button>
        </form>
        <form method="post" action="{{ route('login.resend') }}" class="mt-4 text-center">
            @csrf
            <button class="min-h-11 text-sm font-medium text-slate-600 underline underline-offset-2 hover:text-slate-900">Send a new code</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500"><a href="{{ route('login') }}" class="underline underline-offset-2">Use a different account</a> · No email? Check spam, or ask the school office.</p>
    </div>
</x-layouts.guest>
