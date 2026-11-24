<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public const DEFAULTS = [
        'school_name' => 'My School',
        'school_id' => '',
        'school_address' => '',
        'school_head' => '',
        'school_email' => '',
        'school_phone' => '',
        'logo_path' => '',
        'use_transmutation' => '0',
        'passing_grade' => '75',
        'honors_with_highest' => '98',
        'honors_with_high' => '95',
        'honors_with' => '90',
        'late_after' => '07:30',
        'notify_absent' => '1',
        'notify_late' => '1',
        'library_fine_per_day' => '5',
        'library_loan_days' => '7',
        'privacy_notice' => 'We collect personal information of students and their guardians only for enrollment, academic records, school services and legally required reports, in line with the Data Privacy Act of 2012 (RA 10173). Records are kept securely, shown only to authorized school personnel and the student\'s own guardians, and are never sold or shared without consent unless required by law.',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        static $cache = null;
        if (app()->runningUnitTests()) {
            $cache = null;
        }
        $cache ??= static::query()->pluck('value', 'key')->all();

        return $cache[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
