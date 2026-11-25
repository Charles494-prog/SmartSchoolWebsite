<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        'school_type' => 'private',
        'tuition_mode' => 'per_level',
        'school_id' => '',
        'school_address' => '',
        'school_head' => '',
        'school_email' => '',
        'school_phone' => '',
        'logo_path' => '',
        'use_transmutation' => '0',
        'passing_grade' => '75',
        'grade_scale' => '',
        'grade_scale_show' => 'number',
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

    /** All settings loaded with one query per request; kept in sync by put(). */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (app()->runningUnitTests()) {
            self::$cache = null;
        }
        self::$cache ??= static::query()->pluck('value', 'key')->all();

        return self::$cache[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    /** Forget the per-request copy (e.g. between queued jobs in a long-running worker). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Saves a newly uploaded school logo and removes the one it replaces. */
    public static function storeLogo(UploadedFile $file): void
    {
        $old = self::get('logo_path');
        self::put('logo_path', $file->store('branding', 'local'));
        if ($old && $old !== self::get('logo_path')) {
            Storage::disk('local')->delete($old);
        }
    }

    /** URL of the school logo with a version so browsers refetch after a new upload, or null. */
    public static function logoUrl(): ?string
    {
        $p = self::get('logo_path');

        return $p ? route('school.logo', ['v' => substr(md5($p), 0, 8)]) : null;
    }
}
