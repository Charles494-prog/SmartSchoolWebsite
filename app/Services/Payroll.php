<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Philippine payroll (employee share). Every rate, floor, ceiling and tax bracket is
 * configurable in Payroll → Rates, because government tables change. Defaults are the
 * 2025 tables. HR can still edit each line before finalizing.
 */
class Payroll
{
    /** Contribution settings: percent of salary, clamped to [min, max] salary base (0 = no limit). */
    public const DEFAULTS = [
        'sss' => ['label' => 'SSS', 'rate' => 5.0, 'min' => 5000, 'max' => 35000],
        'philhealth' => ['label' => 'PhilHealth', 'rate' => 2.5, 'min' => 10000, 'max' => 100000],
        'pagibig' => ['label' => 'Pag-IBIG', 'rate' => 2.0, 'min' => 0, 'max' => 10000],
    ];

    /** BIR monthly withholding (TRAIN law, 2023 onwards): [over, base tax, rate % on the excess]. */
    public const DEFAULT_BRACKETS = [[20833, 0, 15], [33333, 1875, 20], [66667, 8541.80, 25], [166667, 33541.80, 30], [666667, 183541.80, 35]];

    private static function config(): array
    {
        if (! function_exists('app') || ! app()->bound('db')) {
            return []; // pure calculations (unit tests) use the defaults
        }
        $saved = json_decode((string) Setting::get('payroll_rates'), true);

        return is_array($saved) ? $saved : [];
    }

    /** @return array<string, array{label: string, rate: float, min: float, max: float}> */
    public static function contributions(): array
    {
        $saved = self::config()['contributions'] ?? [];

        return collect(self::DEFAULTS)->map(fn ($d, $k) => [
            'label' => $d['label'],
            'rate' => (float) ($saved[$k]['rate'] ?? $d['rate']),
            'min' => (float) ($saved[$k]['min'] ?? $d['min']),
            'max' => (float) ($saved[$k]['max'] ?? $d['max']),
        ])->all();
    }

    /** @return list<array{0: float, 1: float, 2: float}> sorted by "over" ascending */
    public static function brackets(): array
    {
        $b = self::config()['brackets'] ?? self::DEFAULT_BRACKETS;

        return collect($b)->map(fn ($r) => [(float) $r[0], (float) $r[1], (float) $r[2]])->sortBy(0)->values()->all();
    }

    public static function save(array $contributions, array $brackets): void
    {
        $c = collect(self::DEFAULTS)->map(fn ($d, $k) => [
            'rate' => round((float) ($contributions[$k]['rate'] ?? $d['rate']), 3),
            'min' => round((float) ($contributions[$k]['min'] ?? 0), 2),
            'max' => round((float) ($contributions[$k]['max'] ?? 0), 2),
        ])->all();
        $b = collect($brackets)->filter(fn ($r) => isset($r['over']) && $r['over'] !== '' && $r['over'] !== null)
            ->map(fn ($r) => [round((float) $r['over'], 2), round((float) ($r['base'] ?? 0), 2), round((float) ($r['rate'] ?? 0), 3)])
            ->sortBy(0)->values()->all();
        Setting::put('payroll_rates', json_encode(['contributions' => $c, 'brackets' => $b]));
    }

    public static function reset(): void
    {
        Setting::put('payroll_rates', '');
    }

    private static function contribution(string $key, float $monthly): float
    {
        $c = self::contributions()[$key];
        $base = max($monthly, $c['min']);
        if ($c['max'] > 0) {
            $base = min($base, $c['max']);
        }

        return round($base * $c['rate'] / 100, 2);
    }

    public static function sss(float $monthly): float
    {
        return self::contribution('sss', $monthly);
    }

    public static function philhealth(float $monthly): float
    {
        return self::contribution('philhealth', $monthly);
    }

    public static function pagibig(float $monthly): float
    {
        return self::contribution('pagibig', $monthly);
    }

    public static function withholding(float $taxable): float
    {
        foreach (array_reverse(self::brackets()) as [$over, $base, $rate]) {
            if ($taxable > $over) {
                return round($base + ($taxable - $over) * $rate / 100, 2);
            }
        }

        return 0.0;
    }

    /** @return array<string, float> line for one employee and a monthly fraction (1 = monthly, 0.5 = semi-monthly) */
    public static function compute(float $monthly, float $fraction = 1.0, float $allowances = 0, float $other = 0): array
    {
        $sssM = self::sss($monthly);
        $phM = self::philhealth($monthly);
        $hdmfM = self::pagibig($monthly);
        $sss = $sssM * $fraction;
        $ph = $phM * $fraction;
        $hdmf = $hdmfM * $fraction;
        $basic = round($monthly * $fraction, 2);
        $tax = round(self::withholding($monthly - $sssM - $phM - $hdmfM) * $fraction, 2);
        $net = round($basic + $allowances - $sss - $ph - $hdmf - $tax - $other, 2);

        return ['basic_pay' => $basic, 'allowances' => $allowances, 'sss' => round($sss, 2), 'philhealth' => round($ph, 2), 'pagibig' => round($hdmf, 2), 'withholding_tax' => $tax, 'other_deductions' => $other, 'net_pay' => $net];
    }
}
