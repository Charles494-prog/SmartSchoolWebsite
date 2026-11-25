<?php

use App\Http\Controllers\AllocationController;
use App\Models\SectionAllocation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('school:backup')->dailyAt('01:00');

// Student section-choice windows: place everyone left once the deadline passes.
Schedule::call(function () {
    SectionAllocation::where('status', 'open')->where('deadline', '<=', now())->get()
        ->each(fn ($a) => AllocationController::closeWindow($a));
})->everyMinute()->name('close-section-choice')->withoutOverlapping();
