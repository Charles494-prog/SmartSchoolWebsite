<x-layouts.app title="Payroll">
    <a href="{{ route('payroll.index') }}" class="link"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />Payroll</a>
    <div class="mt-2 mb-4 flex flex-wrap items-center gap-2">
        <h1 class="h1 flex-1">{{ $p->period_start->format('M d') }} – {{ $p->period_end->format('M d, Y') }} <x-status :value="$p->status" /></h1>
        @if($p->status === 'draft')
            <form method="post" action="{{ route('payroll.finalize', $p) }}" onsubmit="return confirm('Finalize? Amounts can no longer be edited.')">@csrf<button class="btn btn-primary">Finalize</button></form>
            <form method="post" action="{{ route('payroll.destroy', $p) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('delete')<button class="btn">Delete draft</button></form>
        @else
            <a href="{{ route('payroll.payslips', $p) }}" target="_blank" class="btn btn-primary">Print payslips</a>
        @endif
    </div>
    <div class="card overflow-x-auto p-0">
        <table class="table text-xs">
            <thead><tr><th>Employee</th><th>Basic</th><th>Allow.</th><th>SSS</th><th>PhilHealth</th><th>Pag-IBIG</th><th>Tax</th><th>Other</th><th>Net pay</th><th></th></tr></thead>
            <tbody>
            @foreach($p->items as $i)
                <tr>
                    <form method="post" action="{{ route('payroll.items.update', $i) }}">@csrf @method('put')
                    <td class="whitespace-nowrap">{{ $i->employee->full_name }}<div class="text-slate-400">{{ $i->employee->position }}</div></td>
                    @foreach(['basic_pay', 'allowances', 'sss', 'philhealth', 'pagibig', 'withholding_tax', 'other_deductions'] as $f)
                        <td><input name="{{ $f }}" value="{{ number_format($i->$f, 2, '.', '') }}" class="w-20 rounded border border-slate-200 px-1 py-1 text-right" @disabled($p->status !== 'draft')></td>
                    @endforeach
                    <td class="font-bold">{{ number_format($i->net_pay, 2) }}</td>
                    <td>@if($p->status === 'draft')<button class="btn btn-sm">Save</button>@endif</td>
                    </form>
                </tr>
            @endforeach
            <tr class="font-bold"><td>Total</td><td colspan="7"></td><td>{{ number_format($p->items->sum('net_pay'), 2) }}</td><td></td></tr>
            </tbody>
        </table>
    </div>
</x-layouts.app>
