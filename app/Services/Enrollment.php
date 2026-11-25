<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Enrollment as EnrollmentModel;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Support\Photo;
use App\Support\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Enrollment
{
    /**
     * Approve an online application: creates the student, guardian, a parent portal
     * account (if the email is new) and the enrollment. Returns [student, parentPassword|null].
     */
    public static function approve(Application $app, ?Section $section = null): array
    {
        return DB::transaction(function () use ($app, $section) {
            $student = Student::create([
                'lrn' => $app->lrn ?: null,
                'first_name' => $app->first_name,
                'middle_name' => $app->middle_name,
                'last_name' => $app->last_name,
                'suffix' => $app->suffix,
                'sex' => $app->sex,
                'birthdate' => $app->birthdate,
                'birthplace' => $app->birthplace,
                'address' => $app->address,
            ]);

            if ($doc = $app->documents()->where('type', 'photo')->first()) {
                $src = Storage::disk('local')->path($doc->path);
                if (@getimagesize($src)) {
                    $student->update(['photo_path' => Photo::storeSquare($src, 'photos/'.$student->id.'-'.time().'.jpg')]);
                }
            }

            $password = null;
            $user = User::where('email', $app->guardian_email)->first();
            if (! $user) {
                $password = Str::password(10, symbols: false);
                $user = User::create([
                    'name' => $app->guardian_name,
                    'email' => $app->guardian_email,
                    'phone' => $app->guardian_phone,
                    'password' => $password,
                    'role' => Role::PARENT,
                    'must_change_password' => true,
                ]);
            }
            $guardian = Guardian::firstOrCreate(['user_id' => $user->id], ['name' => $app->guardian_name, 'phone' => $app->guardian_phone, 'email' => $app->guardian_email, 'address' => $app->address]);
            $student->guardians()->attach($guardian->id, ['relationship' => $app->guardian_relationship, 'is_primary' => true, 'is_emergency_contact' => true]);

            EnrollmentModel::create([
                'student_id' => $student->id,
                'school_year_id' => $app->school_year_id,
                'grade_level_id' => $app->grade_level_id,
                'section_id' => $section?->id,
                'enrolled_on' => now(),
            ]);
            if ($app->applicant_type === 'transferee' && $app->previous_school) {
                $student->transfers()->create(['direction' => 'in', 'school_name' => $app->previous_school, 'transfer_date' => now()]);
            }

            $app->update(['status' => 'approved', 'student_id' => $student->id, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            $user->notify(new GeneralNotice(
                'Application approved',
                "The application of {$student->first_name} ({$app->reference_no}) was approved. Log in to the parent portal to see the statement of account and class schedule.",
                '/portal',
                true,
            ));

            return [$student, $password];
        });
    }

    /**
     * End-of-year promotion: students whose general average passes move to the next
     * grade level in the target school year; others are retained.
     *
     * @return array{promoted: int, retained: int, graduated: int}
     */
    public static function promote(int $fromYearId, int $toYearId, float $passing): array
    {
        $out = ['promoted' => 0, 'retained' => 0, 'graduated' => 0];
        $enrollments = EnrollmentModel::with(['gradeLevel', 'section', 'student'])->where('school_year_id', $fromYearId)->where('status', 'enrolled')->get();
        foreach ($enrollments as $e) {
            $ga = $e->general_average ?? ($e->section ? Grading::reportCard($e)['general_average'] : null);
            $passed = $ga !== null && $ga >= (float) ($e->gradeLevel->passing_grade ?? $passing);
            $next = $passed ? $e->gradeLevel->next : $e->gradeLevel;
            if ($passed && ! $next) {
                $e->update(['status' => 'graduated', 'general_average' => $ga]);
                $e->student->update(['status' => 'graduated']);
                $out['graduated']++;

                continue;
            }
            $e->update(['status' => $passed ? 'promoted' : 'retained', 'general_average' => $ga]);
            EnrollmentModel::firstOrCreate(
                ['student_id' => $e->student_id, 'school_year_id' => $toYearId],
                ['grade_level_id' => $next->id, 'enrolled_on' => now()],
            );
            $out[$passed ? 'promoted' : 'retained']++;
        }

        return $out;
    }
}
