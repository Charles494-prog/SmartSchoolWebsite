<x-layouts.guest title="Application submitted">
    <div class="mx-auto max-w-lg text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-slate-200"><x-icon name="check" class="h-6 w-6 text-slate-900" /></div>
        <h1 class="mt-6 text-2xl font-semibold text-slate-900">Application submitted</h1>
        <p class="mt-2 text-slate-600">Your reference number:</p>
        <p class="mt-3 rounded-lg border border-slate-200 py-4 font-mono text-2xl font-semibold tracking-widest text-slate-900">{{ $ref }}</p>
        <p class="mt-4 text-sm text-slate-500">Keep this number. Use it with the student's date of birth to check the status. When approved, the parent portal login is emailed to you.</p>
        <a href="{{ route('apply.status') }}" class="mt-8 inline-block rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Check status</a>
    </div>
</x-layouts.guest>
