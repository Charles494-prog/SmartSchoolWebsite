<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\User;
use App\Support\Role;
use Illuminate\Database\Seeder;

/** Base data: grade levels (Kinder–Grade 12), a starter set of subjects and the first admin. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [['Kindergarten', 'kinder', 4, false]];
        foreach (range(1, 6) as $g) {
            $levels[] = ["Grade $g", 'elementary', 4, true];
        }
        foreach (range(7, 10) as $g) {
            $levels[] = ["Grade $g", 'jhs', 4, true];
        }
        $levels[] = ['Grade 11', 'shs', 4, true];
        $levels[] = ['Grade 12', 'shs', 4, true];

        $prev = null;
        foreach ($levels as $i => [$name, $dept, $terms, $numeric]) {
            $gl = GradeLevel::firstOrCreate(['name' => $name], ['department' => $dept, 'sort_order' => $i, 'terms' => $terms, 'numeric_grades' => $numeric]);
            $prev?->update(['next_grade_level_id' => $gl->id]);
            $prev = $gl;
        }

        // A simple starting set of subjects per grade level – rename/add your own in Subjects.
        $basic = ['English', 'Filipino', 'Mathematics', 'Science', 'Social Studies', 'Values Education', 'MAPEH', 'Computer'];
        foreach (GradeLevel::where('numeric_grades', true)->get() as $gl) {
            $n = preg_replace('/\D/', '', $gl->name);
            foreach ($basic as $sub) {
                $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $sub), 0, 4)).$n;
                Subject::firstOrCreate(['code' => $code, 'grade_level_id' => $gl->id], ['name' => $sub, 'ww_weight' => 30, 'pt_weight' => 50, 'qa_weight' => 20]);
            }
        }

        // On a real install (production) the person installing creates the Superadmin on the first-run screen (/setup).
        if (! app()->environment('production') && ! User::where('role', Role::ADMIN)->exists()) {
            User::create(['name' => 'School Administrator', 'email' => 'admin@school.test', 'password' => 'Admin12345', 'role' => Role::ADMIN, 'must_change_password' => true]);
            $this->command?->warn('Admin login: admin@school.test / Admin12345 (you will be asked to change it)');
        }
    }
}
