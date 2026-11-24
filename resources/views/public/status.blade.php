<x-layouts.guest title="Application status">
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-semibold text-slate-900">Check application status</h1>
        <p class="mt-2 text-sm text-slate-500">Enter the reference number you received and the student's date of birth.</p>
        <form class="mt-8 space-y-5">
            <x-field name="reference_no" label="Reference number" :value="request('reference_no')" placeholder="APP-26-XXXXXX" required />
            <x-field name="birthdate" label="Student's date of birth" type="date" :value="request('birthdate')" required />
            <button class="w-full rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Check status</button>
        </form>
        @if($app)
            <div class="mt-8 rounded-lg border border-slate-200 p-5">
                <div class="flex items-center justify-between"><span class="font-semibold">{{ $app->full_name }}</span><x-status :value="$app->status" /></div>
                <p class="mt-1 text-sm text-slate-500">{{ $app->gradeLevel->name }} · submitted {{ $app->created_at->format('M j, Y') }}</p>
                @if($app->remarks)<p class="mt-4 border-t border-slate-200 pt-4 text-sm"><span class="font-medium">Note from the registrar:</span> {{ $app->remarks }}</p>@endif
                @if($app->status === 'approved')<p class="mt-4 border-t border-slate-200 pt-4 text-sm">Approved. Log in to the parent portal with the email you provided.</p>@endif
            </div>
        @endif
    </div>
</x-layouts.guest>
