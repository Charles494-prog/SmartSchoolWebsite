<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Services\Billing;
use App\Support\ParentAccess;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Pay online (Online mode): parents/students pay through the school's GCash/bank channels and upload proof;
 * the cashier approves it, which records the payment and issues the official receipt.
 */
class OnlinePaymentController extends Controller
{
    // ------------------------------------------------------------ admin: payment channels

    public function channels()
    {
        return view('finance.channels', ['channels' => PaymentChannel::orderBy('name')->get()]);
    }

    public function saveChannel(Request $request, ?PaymentChannel $channel = null)
    {
        $d = $request->validate([
            'name' => 'required|max:80', 'kind' => 'required|in:'.implode(',', array_keys(Payment::METHODS)), 'account_name' => 'nullable|max:120',
            'account_number' => 'nullable|max:60', 'instructions' => 'nullable|max:500', 'qr' => 'nullable|image|max:4096', 'is_active' => 'boolean',
        ]);
        if ($request->hasFile('qr')) {
            $d['qr_path'] = $request->file('qr')->store('payment-qr', 'local');
        }
        unset($d['qr']);
        $d['is_active'] = $request->boolean('is_active', true);
        $channel ? $channel->update($d) : PaymentChannel::create($d);

        return back()->with('ok', 'Payment method saved.');
    }

    public function deleteChannel(PaymentChannel $channel)
    {
        $channel->delete();

        return back()->with('ok', 'Payment method removed.');
    }

    public function qr(PaymentChannel $channel)
    {
        abort_unless($channel->qr_path, 404);

        return Storage::disk('local')->response($channel->qr_path);
    }

    // ------------------------------------------------------------ family: submit proof

    private function canPay(User $u, Student $s): bool
    {
        return match ($u->role) {
            Role::STUDENT => $s->user_id === $u->id,
            Role::PARENT => ParentAccess::allows($u, $s, 'pay'),
            default => false,
        };
    }

    public function submit(Request $request, Student $student)
    {
        abort_unless(School::allows('parent_accounts'), 404);
        $u = $request->user();
        abort_unless($this->canPay($u, $student), 403, 'You are not allowed to pay for this student.');
        $account = $student->currentEnrollment()?->account;
        abort_unless($account, 404, 'No balance to pay yet.');
        $d = $request->validate([
            'payment_channel_id' => 'required|exists:payment_channels,id',
            'amount' => 'required|numeric|min:1|max:'.max(1, $account->balance()),
            'reference' => 'required|max:60', 'paid_on' => 'required|date|before_or_equal:today',
            'screenshot' => 'required|image|max:8192', 'purpose' => 'nullable|max:120',
        ], ['amount.max' => 'That is more than the remaining balance ('.Billing::peso($account->balance()).').', 'screenshot.required' => 'Attach a screenshot or photo of your receipt.']);
        $proof = $account->hasMany(PaymentProof::class, 'student_account_id')->create([
            'payment_channel_id' => $d['payment_channel_id'], 'amount' => $d['amount'], 'reference' => $d['reference'], 'paid_on' => $d['paid_on'], 'purpose' => $d['purpose'] ?? null,
            'screenshot_path' => $request->file('screenshot')->store('payment-proofs', 'local'), 'submitted_by' => $u->id,
        ]);
        Notification::send(User::whereIn('role', [Role::CASHIER, Role::ADMIN])->where('is_active', true)->get(),
            new GeneralNotice('Online payment to check', $u->name.' sent '.Billing::peso($proof->amount).' for '.$student->full_name.'.', route('finance.proofs', [], false)));

        return back()->with('ok', 'Thank you! The cashier will check your payment and send the official receipt.');
    }

    public function screenshot(PaymentProof $proof)
    {
        $u = auth()->user();
        $student = $proof->account->enrollment->student;
        abort_unless($u->hasRole(Role::ADMIN, Role::CASHIER) || $proof->submitted_by === $u->id || $this->canPay($u, $student), 403);

        return Storage::disk('local')->response($proof->screenshot_path);
    }

    // ------------------------------------------------------------ cashier: review

    public function proofs(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';

        return view('finance.proofs', [
            'status' => $status,
            'proofs' => PaymentProof::with('account.enrollment.student', 'account.enrollment.section', 'channel', 'submitter')->where('status', $status)->latest()->paginate(20)->withQueryString(),
            'counts' => PaymentProof::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function review(Request $request, PaymentProof $proof)
    {
        abort_unless($proof->status === 'pending', 409, 'Already reviewed.');
        $d = $request->validate(['action' => 'required|in:approve,reject', 'note' => 'nullable|required_if:action,reject|max:255'],
            ['note.required_if' => 'Say why it was not accepted, so the family knows what to fix.']);
        $account = $proof->account;
        $student = $account->enrollment->student;
        if ($d['action'] === 'approve') {
            abort_if($proof->amount > $account->balance() + 0.001, 422, 'This is more than the remaining balance.');
            $payment = DB::transaction(function () use ($proof, $account) {
                $p = $account->payments()->create(['amount' => $proof->amount, 'method' => $proof->channel?->kind ?? 'bank', 'reference' => $proof->reference, 'paid_on' => $proof->paid_on, 'purpose' => $proof->purpose,
                    'or_number' => Billing::nextOrNumber(), 'received_by' => auth()->id()]);
                $proof->update(['status' => 'approved', 'payment_id' => $p->id, 'reviewed_by' => auth()->id()]);

                return $p;
            });
            $proof->submitter?->notify(new GeneralNotice('Payment confirmed', Billing::peso($payment->amount).' for '.$student->first_name.' – OR '.$payment->or_number.'. Remaining balance: '.Billing::peso($account->balance()).'.', '/portal/account'));

            return back()->with('ok', 'Approved – OR '.$payment->or_number.' issued.');
        }
        $proof->update(['status' => 'rejected', 'note' => $d['note'], 'reviewed_by' => auth()->id()]);
        $proof->submitter?->notify(new GeneralNotice('Payment not accepted', Billing::peso($proof->amount).' for '.$student->first_name.': '.$d['note'], '/portal/account'));

        return back()->with('ok', 'Marked as not accepted. The family was told why.');
    }
}
