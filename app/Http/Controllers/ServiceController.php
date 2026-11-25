<?php

namespace App\Http\Controllers;

use App\Models\AccountCharge;
use App\Models\SchoolService;
use App\Models\ServiceRequest;
use App\Models\Student;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Services\Billing;
use App\Support\ParentAccess;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Requests for documents/services (TOR, good moral, certificates…). The registrar sets them up with a fee;
 * a request with a fee goes on the student's balance; the registrar releases it once it's paid.
 */
class ServiceController extends Controller
{
    // ------------------------------------------------------------ registrar

    public function manage(Request $request)
    {
        $status = in_array($request->query('status'), array_keys(ServiceRequest::STATUSES), true) ? $request->query('status') : 'requested';

        return view('services.manage', [
            'services' => SchoolService::orderBy('name')->get(),
            'requests' => ServiceRequest::with('service', 'student', 'charge')->where('status', $status)->latest()->get(),
            'status' => $status,
            'counts' => ServiceRequest::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function save(Request $request, ?SchoolService $service = null)
    {
        $d = $request->validate(['name' => 'required|max:120', 'description' => 'nullable|max:255', 'fee' => 'required|numeric|min:0|max:100000', 'days' => 'required|integer|min:0|max:120', 'is_active' => 'boolean']);
        $d['is_active'] = $request->boolean('is_active', true);
        $service ? $service->update($d) : SchoolService::create($d);

        return back()->with('ok', 'Saved.');
    }

    public function update(Request $request, ServiceRequest $req)
    {
        $d = $request->validate(['status' => 'required|in:processing,ready,released,declined', 'note' => 'nullable|required_if:status,declined|max:255'],
            ['note.required_if' => 'Tell the student why.']);
        if ($d['status'] === 'released') {
            abort_if($req->charge && $req->charge->remaining() > 0, 422, 'Not paid yet – finance must confirm the payment first.');
        }
        if ($d['status'] === 'declined') {
            abort_if($req->charge && $req->charge->paid() > 0, 422, 'Already paid – refund it at finance first.');
            $req->charge?->update(['is_void' => true]);
        }
        $req->update(['status' => $d['status'], 'note' => $d['note'] ?? $req->note]);
        if ($d['status'] === 'ready') {
            if ($req->isPaid()) {
                return back()->with('ok', 'Marked ready. It is paid – you can release it, or notify the family to come get it.');
            }

            return back()->with('ok', 'Marked ready. Not paid yet – you will be notified when finance confirms the payment.');
        }
        $msg = ['processing' => 'is being prepared', 'released' => 'was released to you', 'declined' => 'was not accepted: '.($d['note'] ?? '')][$d['status']];
        $req->requester?->notify(new GeneralNotice('Request update', $req->service->name.' '.$msg, '/portal/services'));

        return back()->with('ok', 'Updated.');
    }

    /** Tell the student and their parents the document is ready (and whether it still needs to be paid). */
    public function notify(ServiceRequest $req)
    {
        abort_unless(in_array($req->status, ['ready', 'processing'], true), 422);
        $paid = $req->isPaid();
        $body = $paid ? $req->service->name.' is ready. You can get it at the registrar\'s office now.'
            : $req->service->name.' is ready, but '.Billing::peso($req->charge->remaining()).' is still unpaid. Please pay first (Payments), then get it at the registrar.';
        $people = collect([$req->student->user])->merge($req->student->guardians()->with('user')->get()->pluck('user'))->filter()->unique('id');
        Notification::send($people, new GeneralNotice('Your document is ready', $body, $paid ? '/portal/services' : '/portal/account'));

        return back()->with('ok', 'Notified '.$people->count().' '.str('account')->plural($people->count()).'.');
    }

    // ------------------------------------------------------------ students / parents

    private function kid(Request $request): ?Student
    {
        $u = $request->user();
        $kids = $u->role === Role::STUDENT ? Student::where('user_id', $u->id)->get() : $u->children()->get();

        return $kids->firstWhere('id', (int) $request->query('child', session('child_id'))) ?? $kids->first();
    }

    public function index(Request $request)
    {
        $child = $this->kid($request);

        return view('services.portal', [
            'child' => $child,
            'services' => SchoolService::where('is_active', true)->orderBy('name')->get(),
            'requests' => $child ? ServiceRequest::with('service', 'charge')->where('student_id', $child->id)->latest()->get() : collect(),
        ]);
    }

    public function request(Request $request, Student $student)
    {
        $u = $request->user();
        abort_unless($u->role === Role::STUDENT ? $student->user_id === $u->id : ParentAccess::allows($u, $student, 'pay'), 403);
        $d = $request->validate(['school_service_id' => 'required|exists:school_services,id', 'copies' => 'required|integer|min:1|max:10', 'purpose' => 'nullable|max:255']);
        $service = SchoolService::where('is_active', true)->findOrFail($d['school_service_id']);
        $total = round($service->fee * $d['copies'], 2);
        $account = $student->currentEnrollment()?->account;
        abort_if($total > 0 && ! $account, 422, 'Your school fees are not set up yet – ask the cashier.');
        DB::transaction(function () use ($d, $service, $student, $u, $total, $account) {
            $req = ServiceRequest::create($d + ['student_id' => $student->id, 'requested_by' => $u->id]);
            if ($total > 0) {
                $c = AccountCharge::create(['student_account_id' => $account->id, 'description' => $service->name.($d['copies'] > 1 ? ' ×'.$d['copies'] : ''), 'amount' => $total]);
                $req->update(['account_charge_id' => $c->id]);
            }
        });
        Notification::send(User::whereIn('role', [Role::REGISTRAR, Role::ADMIN])->where('is_active', true)->get(),
            new GeneralNotice('New request: '.$service->name, $student->full_name, route('services.manage', [], false)));

        return back()->with('ok', $total > 0 ? 'Requested. '.Billing::peso($total).' was added to the balance – pay it on Payments, then the registrar releases it.' : 'Requested. The registrar will tell you when it is ready.');
    }
}
