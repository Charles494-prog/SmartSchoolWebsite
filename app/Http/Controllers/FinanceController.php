<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\SchoolYear;
use App\Models\StudentAccount;
use App\Notifications\GeneralNotice;
use App\Services\Billing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /** Transactions: find a student, see their balance, take a payment. Overdue/settled are filters; receipts are a tab. */
    public function index(Request $request)
    {
        $sy = SchoolYear::current();
        $view = in_array($request->query('view'), ['balances', 'receipts'], true) ? $request->query('view') : 'balances';
        $filter = in_array($request->query('filter'), ['all', 'overdue', 'settled', 'unassessed'], true) ? $request->query('filter') : 'balance';
        $q = Enrollment::with(['student', 'gradeLevel', 'section', 'account.payments', 'account.installments'])->where('school_year_id', $sy?->id)->whereIn('status', ['enrolled', 'promoted', 'retained']);
        if ($t = trim((string) $request->query('q'))) {
            $q->whereHas('student', fn ($s) => $s->where('last_name', 'like', "%$t%")->orWhere('first_name', 'like', "%$t%")->orWhere('student_no', 'like', "%$t%")->orWhere('lrn', 'like', "%$t%"));
        }
        $all = $q->get()->sortBy('student.last_name')->values();
        $rows = $all->filter(fn ($e) => match ($filter) {
            'overdue' => $e->account && $e->account->overdueAmount() > 0,
            'settled' => $e->account && $e->account->balance() <= 0,
            'unassessed' => ! $e->account,
            'all' => true,
            default => ! $e->account || $e->account->balance() > 0,       // default: hide settled balances
        })->values();
        $accounts = StudentAccount::whereHas('enrollment', fn ($e) => $e->where('school_year_id', $sy?->id))->with('payments', 'installments')->get();
        $billed = $accounts->sum('net_amount');
        $collected = (float) Payment::where('is_void', false)->whereIn('student_account_id', $accounts->pluck('id'))->sum('amount');

        return view('finance.index', [
            'rows' => $rows, 'view' => $view, 'filter' => $filter,
            'billed' => $billed, 'collected' => $collected, 'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : 0,
            'counts' => [
                'balance' => $accounts->filter(fn ($a) => $a->balance() > 0)->count() + $all->filter(fn ($e) => ! $e->account)->count(),
                'overdue' => $accounts->filter(fn ($a) => $a->overdueAmount() > 0)->count(),
                'settled' => $accounts->filter(fn ($a) => $a->balance() <= 0)->count(),
                'unassessed' => Enrollment::where('school_year_id', $sy?->id)->whereIn('status', ['enrolled', 'promoted', 'retained'])->doesntHave('account')->count(),
            ],
            'overdueTotal' => $accounts->sum(fn ($a) => $a->overdueAmount()),
            'pendingOnline' => \App\Models\PaymentProof::where('status', 'pending')->count(),
            'receipts' => $view === 'receipts' ? Payment::with('account.enrollment.student', 'receiver')->latest('paid_on')->latest('id')
                ->when($t ?? null, fn ($r, $t) => $r->whereHas('account.enrollment.student', fn ($s) => $s->where('last_name', 'like', "%$t%")->orWhere('first_name', 'like', "%$t%"))->orWhere('or_number', 'like', "%$t%"))
                ->paginate(30)->withQueryString() : null,
        ]);
    }

    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['student', 'gradeLevel', 'schoolYear', 'section', 'account.installments', 'account.payments.receiver', 'account.discounts.discount']);

        $paid = $enrollment->account?->paidAmount() ?? 0;

        return view('finance.show', [
            'plans' => PaymentPlan::options(),
            'paid' => $paid,
            'e' => $enrollment,
            'a' => $enrollment->account,
            'discounts' => Discount::orderBy('name')->get(),
            'fees' => FeeItem::where('school_year_id', $enrollment->school_year_id)->where(fn ($q) => $q->whereNull('grade_level_id')->orWhere('grade_level_id', $enrollment->grade_level_id))->get(),
        ]);
    }

    public function assess(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate(['payment_plan' => 'required|exists:payment_plans,id', 'discount_ids' => 'array', 'discount_ids.*' => 'exists:discounts,id']);
        if ($enrollment->account?->payments()->where('is_void', false)->exists() && $enrollment->account->payment_plan !== $data['payment_plan']) {
            // allowed, but recorded in the audit log by the model
        }
        Billing::assess($enrollment, $data['payment_plan'], $data['discount_ids'] ?? []);

        return back()->with('ok', 'Statement of account updated.');
    }

    /** Adjust the installment schedule by hand (dates and amounts). The total must stay the same. */
    public function schedule(Request $request, Enrollment $enrollment)
    {
        $a = $enrollment->account;
        abort_unless($a, 404);
        $data = $request->validate([
            'rows' => 'required|array|min:1|max:24',
            'rows.*.label' => 'required|max:40',
            'rows.*.due_on' => 'required|date',
            'rows.*.amount' => 'required|numeric|min:0',
        ]);
        $sum = round(collect($data['rows'])->sum(fn ($r) => (float) $r['amount']), 2);
        if (abs($sum - $a->net_amount) > 0.009) {
            return back()->withInput()->with('error', 'The payments add up to '.Billing::peso($sum).' but the total due is '.Billing::peso($a->net_amount).'. Please make them equal.');
        }
        DB::transaction(function () use ($a, $data) {
            $a->installments()->delete();
            foreach (collect($data['rows'])->sortBy('due_on') as $r) {
                $a->installments()->create(['label' => $r['label'], 'due_on' => $r['due_on'], 'amount' => round((float) $r['amount'], 2)]);
            }
        });
        AuditLog::record('schedule', $a, [], ['rows' => $data['rows']], 'Installments edited by hand');

        return back()->with('ok', 'Payment schedule saved.');
    }

    public function pay(Request $request, Enrollment $enrollment)
    {
        $a = $enrollment->account;
        abort_unless($a, 404);
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:'.max(1, $a->balance()),
            'method' => 'required|in:'.implode(',', array_keys(Payment::METHODS)),
            'reference' => 'nullable|required_unless:method,cash|max:60',
            'paid_on' => 'required|date|before_or_equal:today',
        ], ['amount.max' => 'Amount is more than the remaining balance ('.Billing::peso($a->balance()).').', 'reference.required_unless' => 'Enter the reference/check number.']);
        $payment = DB::transaction(fn () => $a->payments()->create($data + ['or_number' => Billing::nextOrNumber(), 'received_by' => auth()->id()]));
        foreach ($enrollment->student->guardians()->whereNotNull('user_id')->with('user')->get() as $g) {
            $g->user?->notify(new GeneralNotice('Payment received', 'We received '.Billing::peso($payment->amount)." for {$enrollment->student->first_name} (OR {$payment->or_number}). Remaining balance: ".Billing::peso($a->balance()).'.', '/portal/account'));
        }

        return back()->with('ok', "Payment recorded. OR {$payment->or_number}.")->with('receipt', $payment->id);
    }

    public function void(Request $request, Payment $payment)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only an administrator can void a receipt.');
        $request->validate(['void_reason' => 'required|max:255']);
        $payment->update(['is_void' => true, 'void_reason' => $request->void_reason]);

        return back()->with('ok', "OR {$payment->or_number} voided.");
    }

    public function receipt(Payment $payment)
    {
        $payment->load('account.enrollment.student', 'account.enrollment.gradeLevel', 'account.enrollment.schoolYear', 'receiver');
        abort_unless(auth()->user()->hasRole('admin', 'cashier') || auth()->user()->canViewStudent($payment->account->enrollment->student), 403);

        return Pdf::loadView('pdf.receipt', ['p' => $payment, 'a' => $payment->account])->setPaper([0, 0, 420, 400])->stream($payment->or_number.'.pdf');
    }

    public function soa(Enrollment $enrollment)
    {
        abort_unless(auth()->user()->hasRole('admin', 'cashier') || auth()->user()->canViewStudent($enrollment->student), 403);
        $enrollment->load('student', 'gradeLevel', 'schoolYear', 'section', 'account.installments', 'account.payments', 'account.discounts.discount');
        abort_unless($enrollment->account, 404);
        $fees = FeeItem::where('school_year_id', $enrollment->school_year_id)->where(fn ($q) => $q->whereNull('grade_level_id')->orWhere('grade_level_id', $enrollment->grade_level_id))->get();

        return Pdf::loadView('pdf.soa', ['e' => $enrollment, 'a' => $enrollment->account, 'fees' => $fees])->setPaper('a4')->stream('SOA-'.$enrollment->student->student_no.'.pdf');
    }

    public function payments(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $payments = Payment::with('account.enrollment.student', 'receiver')->whereBetween('paid_on', [$from, $to])->latest('paid_on')->latest('id')->get();

        return view('finance.payments', ['payments' => $payments, 'from' => $from, 'to' => $to, 'total' => $payments->where('is_void', false)->sum('amount')]);
    }

    public function overdue()
    {
        $sy = SchoolYear::current();
        $rows = StudentAccount::with('enrollment.student.guardians', 'enrollment.gradeLevel', 'enrollment.section', 'installments', 'payments')
            ->whereHas('enrollment', fn ($e) => $e->where('school_year_id', $sy?->id))->get()
            ->map(fn ($a) => ['a' => $a, 'overdue' => $a->overdueAmount(), 'balance' => $a->balance()])
            ->filter(fn ($r) => $r['overdue'] > 0)->sortByDesc('overdue');

        return view('finance.overdue', ['rows' => $rows]);
    }

    public function remind(Request $request)
    {
        $ids = $request->validate(['account_ids' => 'required|array', 'account_ids.*' => 'exists:student_accounts,id'])['account_ids'];
        $n = 0;
        foreach (StudentAccount::with('enrollment.student.guardians.user')->whereIn('id', $ids)->get() as $a) {
            $overdue = $a->overdueAmount();
            if ($overdue <= 0) {
                continue;
            }
            foreach ($a->enrollment->student->guardians as $g) {
                if ($g->user) {
                    $g->user->notify(new GeneralNotice('Payment reminder', "Overdue balance for {$a->enrollment->student->first_name}: ".Billing::peso($overdue).' (total balance '.Billing::peso($a->balance()).'). Please settle at the cashier. Thank you!', '/portal/account', true));
                    $n++;
                }
            }
        }
        AuditLog::record('reminders', null, [], ['accounts' => count($ids), 'notices' => $n], 'Sent overdue reminders');

        return back()->with('ok', "Sent $n reminder(s) (portal + email).");
    }
}
