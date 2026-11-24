<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\Payroll as Calc;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index()
    {
        return view('payroll.index', ['payrolls' => Payroll::withCount('items')->withSum('items', 'net_pay')->latest('period_start')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start']);
        $days = Carbon::parse($data['period_start'])->diffInDays($data['period_end']) + 1;
        $fraction = $days <= 16 ? 0.5 : 1.0;
        $p = Payroll::create($data + ['prepared_by' => auth()->id()]);
        foreach (Employee::where('status', 'active')->get() as $e) {
            $p->items()->create(['employee_id' => $e->id] + Calc::compute($e->monthly_salary, $fraction));
        }

        return redirect()->route('payroll.show', $p)->with('ok', 'Payroll generated ('.($fraction < 1 ? 'semi-monthly' : 'monthly').'). Review the amounts, then finalize.');
    }

    public function show(Payroll $payroll)
    {
        return view('payroll.show', ['p' => $payroll->load('items.employee')]);
    }

    public function updateItem(Request $request, PayrollItem $item)
    {
        abort_if($item->payroll->status === 'finalized', 403, 'This payroll is finalized.');
        $data = $request->validate(collect(['basic_pay', 'allowances', 'sss', 'philhealth', 'pagibig', 'withholding_tax', 'other_deductions'])->mapWithKeys(fn ($f) => [$f => 'required|numeric|min:0'])->all());
        $data['net_pay'] = round($data['basic_pay'] + $data['allowances'] - $data['sss'] - $data['philhealth'] - $data['pagibig'] - $data['withholding_tax'] - $data['other_deductions'], 2);
        $item->update($data);

        return back()->with('ok', 'Updated '.$item->employee->full_name.'.');
    }

    public function finalize(Payroll $payroll)
    {
        $payroll->update(['status' => 'finalized']);

        return back()->with('ok', 'Payroll finalized. Payslips can now be printed.');
    }

    public function destroy(Payroll $payroll)
    {
        abort_if($payroll->status === 'finalized', 403);
        $payroll->delete();

        return redirect()->route('payroll.index')->with('ok', 'Draft payroll deleted.');
    }

    public function payslips(Payroll $payroll)
    {
        return Pdf::loadView('pdf.payslips', ['p' => $payroll->load('items.employee')])->setPaper('a4')->stream('payslips-'.$payroll->period_end->format('Y-m-d').'.pdf');
    }
}
