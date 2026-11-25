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

    public function rates()
    {
        return view('payroll.rates', ['contributions' => Calc::contributions(), 'brackets' => Calc::brackets()]);
    }

    public function saveRates(Request $request)
    {
        $keys = array_keys(Calc::DEFAULTS);
        $rules = ['brackets' => 'array|max:15', 'brackets.*.over' => 'nullable|numeric|min:0|max:100000000', 'brackets.*.base' => 'nullable|numeric|min:0|max:100000000', 'brackets.*.rate' => 'nullable|numeric|min:0|max:100'];
        foreach ($keys as $k) {
            $rules["c.$k.rate"] = 'required|numeric|min:0|max:100';
            $rules["c.$k.min"] = 'required|numeric|min:0|max:100000000';
            $rules["c.$k.max"] = 'required|numeric|min:0|max:100000000';
        }
        $data = $request->validate($rules, ['c.*.rate.max' => 'A rate cannot be more than 100%.']);
        foreach ($keys as $k) {
            if ($data['c'][$k]['max'] > 0 && $data['c'][$k]['max'] < $data['c'][$k]['min']) {
                return back()->withInput()->withErrors(["c.$k.max" => 'The ceiling must be higher than the floor (or 0 for no ceiling).']);
            }
        }
        Calc::save($data['c'], $data['brackets'] ?? []);

        return redirect()->route('payroll.rates')->with('ok', 'Payroll rates saved. New payrolls use them; finalized payrolls are not changed.');
    }

    public function resetRates()
    {
        Calc::reset();

        return redirect()->route('payroll.rates')->with('ok', 'Rates reset to the built-in government tables.');
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
