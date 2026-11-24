<?php

namespace App\Services;

/**
 * Philippine payroll estimates (employee share). Rates change yearly – the
 * cashier/HR can edit each line before finalizing.
 */
class Payroll
{
    public static function sss(float $monthly): float
    {
        return round(min(max($monthly, 5000), 35000) * 0.05, 2);
    }

    public static function philhealth(float $monthly): float
    {
        return round(min(max($monthly, 10000), 100000) * 0.025, 2);
    }

    public static function pagibig(float $monthly): float
    {
        return round(min($monthly, 10000) * 0.02, 2);
    }

    /** BIR monthly withholding tax (TRAIN law, 2023 onwards). */
    public static function withholding(float $taxable): float
    {
        // [over, base tax, rate on the excess]
        $brackets = [[666667, 183541.80, 0.35], [166667, 33541.80, 0.30], [66667, 8541.80, 0.25], [33333, 1875, 0.20], [20833, 0, 0.15]];
        foreach ($brackets as [$over, $base, $rate]) {
            if ($taxable > $over) {
                return round($base + ($taxable - $over) * $rate, 2);
            }
        }

        return 0.0;
    }

    /** @return array<string, float> line for one employee and a monthly fraction (1 = monthly, 0.5 = semi-monthly) */
    public static function compute(float $monthly, float $fraction = 1.0, float $allowances = 0, float $other = 0): array
    {
        $sss = self::sss($monthly) * $fraction;
        $ph = self::philhealth($monthly) * $fraction;
        $hdmf = self::pagibig($monthly) * $fraction;
        $basic = round($monthly * $fraction, 2);
        $taxable = ($monthly - self::sss($monthly) - self::philhealth($monthly) - self::pagibig($monthly));
        $tax = round(self::withholding($taxable) * $fraction, 2);
        $net = round($basic + $allowances - $sss - $ph - $hdmf - $tax - $other, 2);

        return ['basic_pay' => $basic, 'allowances' => $allowances, 'sss' => round($sss, 2), 'philhealth' => round($ph, 2), 'pagibig' => round($hdmf, 2), 'withholding_tax' => $tax, 'other_deductions' => $other, 'net_pay' => $net];
    }
}
