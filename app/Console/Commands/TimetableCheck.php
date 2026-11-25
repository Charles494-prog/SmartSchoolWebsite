<?php

namespace App\Console\Commands;

use App\Models\ScheduleSlot;
use App\Services\Timetable;
use Illuminate\Console\Command;

class TimetableCheck extends Command
{
    protected $signature = 'timetable:check {--limit=50 : How many conflicts to list}';

    protected $description = 'List every double-booked room, teacher or section in this school year';

    public function handle(): int
    {
        $c = Timetable::conflicts();
        if ($c->isEmpty()) {
            $this->info('Conflicts: 0. No room, teacher or section is double-booked.');

            return self::SUCCESS;
        }
        $this->error("Conflicts: {$c->count()} (rooms {$c->where('type', 'room')->count()}, teachers {$c->where('type', 'teacher')->count()}, sections {$c->where('type', 'section')->count()})");
        $this->table(['Type', 'Day', 'Time', 'What'], $c->take((int) $this->option('limit'))->map(fn ($x) => [ucfirst($x['type']), ScheduleSlot::DAYS[$x['day']], ampm($x['start']).'–'.ampm($x['end']), $x['text']])->all());

        return self::FAILURE;
    }
}
