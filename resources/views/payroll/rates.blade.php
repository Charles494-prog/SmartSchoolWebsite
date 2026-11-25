<x-layouts.app title="Payroll rates">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('payroll.index') }}" class="mb-3 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-900"><x-icon name="arrow-left" class="h-4 w-4" />Payroll</a>
        <h1 class="h1">Payroll rates</h1>
        <p class="muted mt-1 mb-6">Government contribution rates and tax brackets change from time to time. Update them here when a new circular comes out. New payrolls use these numbers; finalized payrolls stay as they were.</p>

        <form method="post" action="{{ route('payroll.rates.update') }}" class="space-y-5" x-data="{ busy: false }" x-on:submit="busy = true">
            @csrf @method('put')
            <section class="card p-0">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-white/10">
                    <h2 class="h2">Employee contributions</h2>
                    <p class="text-sm text-slate-500">Rate × monthly salary. The salary used is raised to the floor and capped at the ceiling. Use 0 for "no ceiling".</p>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-white/10">
                    @foreach($contributions as $k => $c)
                        <div class="grid gap-4 px-6 py-5 sm:grid-cols-[8rem_1fr_1fr_1fr] sm:items-start">
                            <div class="pt-2 text-sm font-semibold text-slate-900">{{ $c['label'] }}</div>
                            <x-field name="c[{{ $k }}][rate]" label="Rate (%)" type="number" step="0.001" :value="$c['rate']" required />
                            <x-field name="c[{{ $k }}][min]" label="Salary floor (₱)" type="number" step="0.01" :value="$c['min']" required />
                            <x-field name="c[{{ $k }}][max]" label="Salary ceiling (₱)" type="number" step="0.01" :value="$c['max']" required />
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-0" x-data="{ rows: @js(collect(old('brackets') ?? collect($brackets)->map(fn ($b) => ['over' => $b[0], 'base' => $b[1], 'rate' => $b[2]]))->values()) }">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-white/10">
                    <h2 class="h2">Withholding tax (monthly)</h2>
                    <p class="text-sm text-slate-500">Taxable pay = salary − contributions. If it is over an amount, tax = fixed tax + rate × the part above it. Below the lowest amount, no tax.</p>
                </div>
                <div class="space-y-2 px-6 py-5">
                    <div class="hidden grid-cols-[1fr_1fr_1fr_2.75rem] gap-2 text-xs font-semibold text-slate-500 sm:grid"><span>Taxable pay over (₱)</span><span>Fixed tax (₱)</span><span>Rate on the excess (%)</span><span></span></div>
                    <template x-for="(r, i) in rows" :key="i">
                        <div class="grid grid-cols-[1fr_1fr_1fr_2.75rem] gap-2">
                            <input class="input min-h-11" type="number" step="0.01" min="0" :name="'brackets[' + i + '][over]'" x-model="r.over" aria-label="Taxable pay over">
                            <input class="input min-h-11" type="number" step="0.01" min="0" :name="'brackets[' + i + '][base]'" x-model="r.base" aria-label="Fixed tax">
                            <input class="input min-h-11" type="number" step="0.001" min="0" max="100" :name="'brackets[' + i + '][rate]'" x-model="r.rate" aria-label="Rate on the excess">
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600" x-on:click="if (confirm('Remove this tax bracket?')) rows.splice(i, 1)" aria-label="Remove bracket"><x-icon name="trash" class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm min-h-11" x-on:click="rows.push({ over: '', base: '', rate: '' })"><x-icon name="plus" class="h-4 w-4" /> Add bracket</button>
                </div>
            </section>

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/90">
                <button type="submit" form="reset-rates" class="btn min-h-11 mr-auto">Reset to built-in tables</button>
                <a href="{{ route('payroll.index') }}" class="btn min-h-11">Cancel</a>
                <button class="btn btn-primary min-h-11" :disabled="busy"><span x-text="busy ? 'Saving…' : 'Save rates'">Save rates</span></button>
            </div>
        </form>
        <form id="reset-rates" method="post" action="{{ route('payroll.rates.reset') }}" x-data x-on:submit="if (!confirm('Replace your rates with the built-in government tables?')) $event.preventDefault()">@csrf</form>
    </div>
</x-layouts.app>
