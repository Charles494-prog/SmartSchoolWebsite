<x-layouts.app title="Not shared">
    @include('portal._switch')
    <div class="mx-auto mt-10 max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800"><x-icon name="shield" class="h-6 w-6" /></span>
        <h1 class="mt-4 text-xl font-bold">Not shared with you</h1>
        <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $child?->first_name }} hasn't turned on “{{ $what }}” for your account. They can change it in their Personal setup, or ask the school's registrar.</p>
    </div>
</x-layouts.app>
