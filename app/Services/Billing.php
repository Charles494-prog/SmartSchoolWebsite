<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\StudentAccount;
use Carbon\Carbon;

class Billing
{
    public static function plan(string $plan): ?PaymentPlan
    {
        $legacy = ['full' => 'Full payment', 'semestral' => 'Semestral (2 payments)', 'quarterly' => 'Quarterly (4 payments)', 'monthly' => 'Monthly (10 payments)'];

        return is_numeric($plan) ? PaymentPlan::find((int) $plan) : PaymentPlan::where('name', $legacy[$plan] ?? $plan)->first();
    }

    /**
     * Create or recompute the statement of account for an enrollment.
     *
     * @param  array<int>  $discountIds
     */
    public static function assess(Enrollment $e, string|int|null $plan = null, array $discountIds = []): StudentAccount
    {
        $planModel = self::plan((string) ($plan ?? '')) ?? PaymentPlan::orderBy('installments')->first();
        $plan = (string) $planModel?->id;
        $fees = FeeItem::where('school_year_id', $e->school_year_id)
            ->where(fn ($q) => $q->whereNull('grade_level_id')->orWhere('grade_level_id', $e->grade_level_id))
            ->get();
        $gross = round($fees->sum('amount'), 2);
        $tuition = round($fees->where('category', 'tuition')->sum('amount'), 2);

        $account = StudentAccount::firstOrNew(['enrollment_id' => $e->id]);
        $account->payment_plan = $plan;
        $account->gross_amount = $gross;
        $account->save();

        $account->discounts()->delete();
        $discountTotal = 0.0;
        foreach (Discount::whereIn('id', $discountIds)->get() as $d) {
            $base = $d->tuition_only ? $tuition : $gross;
            $amt = $d->type === 'percent' ? round($base * $d->value / 100, 2) : min($d->value, $base);
            $account->discounts()->create(['discount_id' => $d->id, 'amount' => $amt]);
            $discountTotal += $amt;
        }
        $discountTotal = min($discountTotal, $gross);
        $surcharge = $planModel ? round(($gross - $discountTotal) * $planModel->surcharge_percent / 100, 2) : 0;
        $account->update(['discount_amount' => $discountTotal, 'net_amount' => round($gross - $discountTotal + $surcharge, 2)]);

        self::buildInstallments($account, Carbon::parse($e->loadMissing('schoolYear')->schoolYear->starts_on));

        return $account->fresh();
    }

    /** Split the net amount by the plan: optional down payment, the rest in equal parts. */
    public static function buildInstallments(StudentAccount $account, Carbon $start): void
    {
        $account->installments()->delete();
        $plan = self::plan((string) $account->payment_plan);
        $n = max(1, $plan?->installments ?? 1);
        $gap = max(1, $plan?->months_between ?? 1);
        $total = $account->net_amount;
        $down = $n > 1 && $plan?->downpayment_percent > 0 ? round($total * $plan->downpayment_percent / 100, 2) : null;
        $restN = $down !== null ? $n - 1 : $n;
        $rest = $down !== null ? $total - $down : $total;
        $each = floor($rest / $restN * 100) / 100;
        for ($i = 0; $i < $n; $i++) {
            if ($down !== null && $i === 0) {
                $amount = $down;
            } else {
                $k = $down !== null ? $i - 1 : $i;
                $amount = $k === $restN - 1 ? round($rest - $each * ($restN - 1), 2) : $each;
            }
            $account->installments()->create([
                'label' => $n === 1 ? 'Full payment' : ($down !== null && $i === 0 ? 'Down payment' : 'Payment '.($i + 1).' of '.$n),
                'due_on' => $start->copy()->addMonthsNoOverflow($i * $gap),
                'amount' => $amount,
            ]);
        }
    }

    public static function nextOrNumber(): string
    {
        $prefix = 'OR-'.now()->format('Y').'-';
        $last = Payment::where('or_number', 'like', $prefix.'%')->max('or_number');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    public static function peso(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }

    /**
     * Split a payment between bookstore orders/other charges and tuition.
     * "order:<charge id>" pays that order first; tuition choices (down payment, installment, overdue, a fee) pay tuition first;
     * anything else (full balance, another amount) clears orders first, oldest first, and the rest goes to tuition.
     */
    public static function allocate(\App\Models\Payment $payment, ?string $key = null): void
    {
        $account = $payment->account;
        $amount = (float) $payment->amount;
        $charges = $account->charges()->orderBy('created_at')->get()->filter(fn ($c) => $c->remaining() > 0)->values();
        if ($key && str_starts_with($key, 'order:')) {
            $first = (int) substr($key, 6);
            $charges = $charges->sortBy(fn ($c) => $c->id === $first ? 0 : 1)->values();
        }
        $left = $amount;
        if ($key && (in_array($key, ['down', 'overdue', 'next'], true) || str_starts_with($key, 'fee:'))) {
            // Tuition first: only money beyond what tuition still needed goes to orders.
            $tuitionOwedBefore = max(0, round($account->net_amount - ($account->tuitionPaid() - $amount), 2));
            $left = max(0, round($amount - $tuitionOwedBefore, 2));
        }
        foreach ($charges as $c) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, $c->remaining());
            \App\Models\PaymentAllocation::create(['payment_id' => $payment->id, 'account_charge_id' => $c->id, 'amount' => $take]);
            $left = round($left - $take, 2);
        }
    }
}
