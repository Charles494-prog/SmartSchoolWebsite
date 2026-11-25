<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\DemoNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Give every DEMO student (seeded LRN 14000…) a different name. Real students are never touched. */
class DemoUniqueNames extends Command
{
    protected $signature = 'demo:unique-names {--force : Skip the confirmation}';

    protected $description = 'Rename demo students (and their demo login/parent names) so no two share a name';

    public function handle(): int
    {
        $demo = Student::where('lrn', 'like', '14000%')->whereRaw('length(lrn) = 12')->with('user', 'guardians.user')->get();
        if ($demo->isEmpty()) {
            $this->info('No demo students found. Nothing changed.');

            return self::SUCCESS;
        }
        $real = Student::count() - $demo->count();
        if ($real > 0) {
            $this->warn("$real non-demo student(s) exist. They will NOT be changed.");
        }
        if (! $this->option('force') && ! $this->confirm("Rename {$demo->count()} demo students?")) {
            return self::FAILURE;
        }
        DB::transaction(function () use ($demo) {
            foreach ($demo as $st) {
                [$f, $m, $l] = DemoNames::for((int) substr($st->lrn, 1) - 40000000000);
                $oldParent = 'Parent of '.$st->first_name.' '.$st->last_name;
                $st->update(['first_name' => $f, 'middle_name' => $m, 'last_name' => $l]);
                if ($st->user && str_ends_with($st->user->email, '@school.test')) {
                    $st->user->update(['name' => "$f $l"]);
                }
                foreach ($st->guardians as $g) {
                    if ($g->name === $oldParent) {
                        $g->update(['name' => "Parent of $f $l"]);
                        $g->user?->update(['name' => "Parent of $f $l"]);
                    }
                }
            }
        });
        $dupes = Student::get(['first_name', 'last_name'])->countBy(fn ($s) => mb_strtolower($s->first_name.' '.$s->last_name))->filter(fn ($c) => $c > 1)->count();
        $this->info("Renamed {$demo->count()} demo students. Students sharing a first + last name now: $dupes.");

        return self::SUCCESS;
    }
}
