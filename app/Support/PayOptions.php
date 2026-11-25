<?php

namespace App\Support;

use App\Models\PaymentProof;
use App\Models\StudentAccount;

/** The quick choices for "what are you paying for?" – used by families online and by the cashier. */
class PayOptions
{
    /** @return array<int, array{key: string, label: string, hint: string, amount: float}> */
    public static function for(StudentAccount $a): array
    {
        $bal = round($a->balance(), 2);
        $opts = [];
        $paid = $a->tuitionPaid();
        foreach ($a->charges()->with('order.lines')->orderBy('created_at')->get() as $c) {
            if (($r = $c->remaining()) > 0) {
                $opts[] = ['key' => 'order:'.$c->id, 'label' => $c->description, 'hint' => $c->order ? $c->order->summary() : 'Extra charge', 'amount' => $r];
            }
        }
        $first = $a->installments->sortBy('due_on')->first();
        if ($paid <= 0 && $first && $a->installments->count() > 1) {   // a single "full payment" plan has no down payment
            $opts[] = ['key' => 'down', 'label' => 'Down payment', 'hint' => 'First tuition payment, due '.$first->due_on->format('M d'), 'amount' => min($a->tuitionBalance(), (float) $first->amount)];
        }
        if (($od = round($a->overdueAmount(), 2)) > 0 && $a->tuitionBalance() > 0) {
            $opts[] = ['key' => 'overdue', 'label' => 'Overdue amount', 'hint' => 'Everything past due today', 'amount' => min($bal, $od)];
        }
        // next unpaid installment (installments are paid in order)
        $covered = $paid;
        foreach ($a->installments->sortBy('due_on') as $i) {
            if ($covered >= $i->amount - 0.005) {
                $covered -= $i->amount;

                continue;
            }
            if ($paid > 0) {
                $opts[] = ['key' => 'next', 'label' => 'Tuition · '.$i->label, 'hint' => 'Due '.$i->due_on->format('M d, Y'), 'amount' => min($a->tuitionBalance(), round($i->amount - $covered, 2))];
            }
            break;
        }
        if ($bal > 0) {
            $opts[] = ['key' => 'full', 'label' => 'Full balance', 'hint' => 'Tuition and orders – everything left', 'amount' => $bal];
        }
        $e = $a->enrollment;
        foreach ($a->fees()->orderBy('name')->get() as $f) {
            if ($f->amount > 0 && $f->amount <= $a->tuitionBalance()) {
                $opts[] = ['key' => 'fee:'.$f->id, 'label' => $f->name, 'hint' => 'Just this school fee', 'amount' => (float) $f->amount];
            }
        }

        // Options with the same amount are one choice: keep the broadest and mention the others.
        $rank = fn ($k) => match (true) {
            $k === 'full' => 0, $k === 'overdue' => 1, $k === 'next' => 2, $k === 'down' => 3, default => 4
        };
        $tuitionKeys = ['full', 'overdue', 'next', 'down'];
        $merged = collect($opts)->unique('key')->groupBy(fn ($o) => in_array($o['key'], $tuitionKeys, true) ? 't'.number_format($o['amount'], 2) : $o['key'])
            ->map(function ($g) use ($rank) {
                $g = $g->sortBy(fn ($o) => $rank($o['key']))->values();
                $main = $g->first();
                if ($g->count() > 1) {
                    $main['hint'] = 'Also the '.$g->slice(1)->map(fn ($o) => mb_strtolower(preg_replace('/^Tuition · /', '', $o['label'])))->implode(' and the ');
                }

                return $main;
            });

        return $merged->values()->all();
    }

    /** Item keys tied up in online payments that Finance has not reviewed yet. 'full'/'other' tie up everything. */
    public static function pendingKeys(StudentAccount $a): array
    {
        return PaymentProof::where('student_account_id', $a->id)->where('status', 'pending')->pluck('pay_key')
            ->flatMap(fn ($k) => array_filter(explode(',', (string) ($k ?: 'other'))))->unique()->values()->all();
    }

    public static function clashesWithPending(StudentAccount $a, ?string $key): bool
    {
        $pending = self::pendingKeys($a);
        if ($pending === []) {
            return false;
        }
        $keys = array_filter(explode(',', (string) ($key ?: 'other')));
        if (array_intersect($pending, ['full', 'other']) || array_intersect($keys, ['full', 'other'])) {
            return true;
        }

        return (bool) array_intersect($pending, $keys);
    }
}
