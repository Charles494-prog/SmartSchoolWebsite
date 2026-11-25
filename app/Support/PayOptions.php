<?php

namespace App\Support;

use App\Models\FeeItem;
use App\Models\StudentAccount;

/** The quick choices for "what are you paying for?" – used by families online and by the cashier. */
class PayOptions
{
    /** @return array<int, array{key: string, label: string, hint: string, amount: float}> */
    public static function for(StudentAccount $a): array
    {
        $bal = round($a->balance(), 2);
        $opts = [];
        $paid = $a->paidAmount();
        $first = $a->installments->sortBy('due_on')->first();
        if ($paid <= 0 && $first) {
            $opts[] = ['key' => 'down', 'label' => 'Down payment', 'hint' => 'First payment, due '.$first->due_on->format('M d'), 'amount' => min($bal, (float) $first->amount)];
        }
        if (($od = round($a->overdueAmount(), 2)) > 0) {
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
                $opts[] = ['key' => 'next', 'label' => $i->label, 'hint' => 'Due '.$i->due_on->format('M d, Y'), 'amount' => min($bal, round($i->amount - $covered, 2))];
            }
            break;
        }
        if ($bal > 0) {
            $opts[] = ['key' => 'full', 'label' => 'Full balance', 'hint' => 'Pay everything left', 'amount' => $bal];
        }
        $e = $a->enrollment;
        foreach (FeeItem::where('school_year_id', $e->school_year_id)->where(fn ($q) => $q->whereNull('grade_level_id')->orWhere('grade_level_id', $e->grade_level_id))->orderBy('name')->get() as $f) {
            if ($f->amount > 0 && $f->amount <= $bal) {
                $opts[] = ['key' => 'fee:'.$f->id, 'label' => $f->name, 'hint' => 'Just this fee', 'amount' => (float) $f->amount];
            }
        }

        return collect($opts)->unique('key')->values()->all();
    }
}
