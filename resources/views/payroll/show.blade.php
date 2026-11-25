@php
    $fields = ['basic_pay' => 'Basic pay', 'allowances' => 'Allowances', 'sss' => 'SSS', 'philhealth' => 'PhilHealth', 'pagibig' => 'Pag-IBIG', 'withholding_tax' => 'Withholding tax', 'other_deductions' => 'Other deductions'];
    $rows = $p->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->employee->full_name, 'no' => $i->employee->employee_no, 'position' => $i->employee->position, 'dept' => $i->employee->department,
        'gross' => round($i->basic_pay + $i->allowances, 2), 'ded' => round($i->sss + $i->philhealth + $i->pagibig + $i->withholding_tax + $i->other_deductions, 2), 'net' => (float) $i->net_pay,
        'f' => collect($fields)->keys()->mapWithKeys(fn ($f) => [$f => (float) $i->$f])->all(), 'save' => route('payroll.items.update', $i), 'pdf' => route('payroll.payslip', $i)])->sortBy('name')->values();
    $step = ['draft' => 2, 'finalized' => 4, 'paid' => 5][$p->status] ?? 2;
@endphp
<x-layouts.app title="Payroll">
    <x-page-header :title="$p->period_start->format('M j').' – '.$p->period_end->format('M j, Y')" :back="route('payroll.index')" back-label="Payroll">
        <label class="sr-only" for="period">Pay period</label>
        <select id="period" class="input min-h-11 w-auto" x-data x-on:change="location = $el.value">
            @foreach($periods as $o)<option value="{{ route('payroll.show', $o) }}" @selected($o->id === $p->id)>{{ $o->period_start->format('M j') }} – {{ $o->period_end->format('M j, Y') }}</option>@endforeach
        </select>
    </x-page-header>

    <ol class="mb-5 grid grid-cols-5 gap-2 text-xs sm:text-sm" aria-label="Run payroll steps">
        @foreach(['Period', 'Review', 'Adjust', 'Approve', 'Payslips'] as $n => $s)
            <li @if($n + 1 === min($step, 5)) aria-current="step" @endif><span class="mb-1.5 block h-1.5 rounded-full {{ $n + 1 <= $step ? 'bg-brand-600' : 'bg-slate-200 dark:bg-slate-700' }}"></span><span class="{{ $n + 1 <= $step ? 'font-semibold' : 'text-slate-500' }}">{{ $s }}</span></li>
        @endforeach
    </ol>

    <div x-data="{ q: '', st: '', open: null, rows: @js($rows), peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                   get shown() { const q = this.q.toLowerCase(); return this.rows.filter((r) => !q || (r.name + ' ' + r.no + ' ' + (r.position || '') + ' ' + (r.dept || '')).toLowerCase().includes(q)) },
                   sum(k) { return this.shown.reduce((t, r) => t + r[k], 0) } }" x-on:keydown.escape.window="open = null">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <label class="relative min-w-0 flex-1 basis-56"><span class="sr-only">Search employees</span><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input type="search" x-model="q" class="input min-h-11 pl-9" placeholder="Search name, ID, position or department"></label>
            @include('payroll._chip', ['status' => $p->status])
            @if($p->status === 'draft')
                <form method="post" action="{{ route('payroll.destroy', $p) }}" x-data x-on:submit="if (!confirm('Delete this draft payroll? This cannot be undone.')) $event.preventDefault()">@csrf @method('delete')<button class="btn min-h-11 text-rose-700">Delete draft</button></form>
                <form method="post" action="{{ route('payroll.finalize', $p) }}" x-data x-on:submit="if (!confirm('Approve this payroll? Amounts can no longer be edited.')) $event.preventDefault()">@csrf<button class="btn btn-primary min-h-11">Approve payroll</button></form>
            @else
                <a href="{{ route('payroll.payslips', $p) }}" target="_blank" class="btn {{ $p->status === 'finalized' ? '' : 'btn-primary' }} min-h-11"><x-icon name="download" class="h-4 w-4" /> Generate all payslips (PDF)</a>
                @if($p->status === 'finalized')
                    <form method="post" action="{{ route('payroll.paid', $p) }}" x-data x-on:submit="if (!confirm('Mark this payroll as paid?')) $event.preventDefault()">@csrf<button class="btn btn-primary min-h-11">Mark as paid</button></form>
                @endif
            @endif
        </div>
        @if($p->status === 'draft')<p class="mb-3 text-sm text-slate-600 dark:text-slate-400">Review each employee. Click a row to see the breakdown and adjust amounts, then approve.</p>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5"><tr><th class="px-4 py-2.5">Employee</th><th class="px-3 py-2.5 text-right">Gross</th><th class="px-3 py-2.5 text-right">Deductions</th><th class="px-3 py-2.5 text-right">Net pay</th><th class="px-3 py-2.5">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="r in shown" :key="r.id">
                            <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5" x-on:click="open = r" x-on:keydown.enter="open = r" tabindex="0" :aria-label="'Open payslip of ' + r.name">
                                <td class="px-4 py-2.5"><span class="block break-words font-medium" x-text="r.name"></span><span class="text-xs text-slate-500" x-text="[r.no, r.position].filter(Boolean).join(' · ')"></span></td>
                                <td class="px-3 py-2.5 text-right tabular-nums" x-text="peso(r.gross)"></td>
                                <td class="px-3 py-2.5 text-right tabular-nums" x-text="peso(r.ded)"></td>
                                <td class="px-3 py-2.5 text-right font-semibold tabular-nums" x-text="peso(r.net)"></td>
                                <td class="px-3 py-2.5">@include('payroll._chip', ['status' => $p->status])</td>
                            </tr>
                        </template>
                        <tr x-show="!shown.length"><td colspan="5" class="px-4 py-10 text-center text-slate-500" x-text="rows.length ? 'No employee matches.' : 'No employees in this payroll.'"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="sticky bottom-0 flex flex-wrap items-center justify-end gap-x-6 gap-y-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-800">
                <span><span x-text="shown.length"></span> employees</span>
                <span>Gross <b class="tabular-nums" x-text="peso(sum('gross'))"></b></span>
                <span>Deductions <b class="tabular-nums" x-text="peso(sum('ded'))"></b></span>
                <span>Net pay <b class="text-base tabular-nums" x-text="peso(sum('net'))"></b></span>
            </div>
        </div>

        {{-- payslip side panel --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-slate-900/40" x-on:click="open = null"></div>
        <aside x-show="open" x-cloak x-transition class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="slip-title">
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div><h2 id="slip-title" class="h2 break-words" x-text="open?.name"></h2><p class="text-sm text-slate-500" x-text="[open?.no, open?.position].filter(Boolean).join(' · ')"></p></div>
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-white/10" x-on:click="open = null" aria-label="Close"><x-icon name="x" class="h-5 w-5" /></button>
            </header>
            <form method="post" :action="open?.save" class="flex min-h-0 flex-1 flex-col" x-data="{ busy: false }" x-on:submit="busy = true">@csrf @method('put')
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <dl class="space-y-2 text-sm">
                        @foreach($fields as $f => $label)
                            @if($f === 'sss')<dt class="border-t border-slate-200 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800">Deductions</dt>@endif
                            <div class="flex items-center justify-between gap-3"><dt><label for="f-{{ $f }}">{{ $label }}</label></dt>
                                <dd>@if($p->status === 'draft')<input id="f-{{ $f }}" name="{{ $f }}" type="number" step="0.01" min="0" class="input min-h-10 w-32 text-right tabular-nums" :value="open?.f.{{ $f }}.toFixed(2)" required>
                                    @else<span class="tabular-nums" x-text="peso(open?.f.{{ $f }} ?? 0)"></span>@endif</dd></div>
                        @endforeach
                        <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-bold dark:border-slate-800"><dt>Net pay</dt><dd class="tabular-nums" x-text="peso(open?.net ?? 0)"></dd></div>
                    </dl>
                    @if($p->status === 'draft')<p class="mt-3 text-xs text-slate-600 dark:text-slate-400">Net pay is recomputed when you save (gross minus deductions).</p>@endif
                </div>
                <footer class="flex gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    @if($p->status === 'draft')<button class="btn btn-primary min-h-11 flex-1" :disabled="busy" x-text="busy ? 'Saving…' : 'Save changes'">Save changes</button>
                    @else<a :href="open?.pdf" target="_blank" class="btn btn-primary min-h-11 flex-1"><x-icon name="download" class="h-4 w-4" /> Payslip PDF</a>@endif
                </footer>
            </form>
        </aside>
    </div>
</x-layouts.app>
