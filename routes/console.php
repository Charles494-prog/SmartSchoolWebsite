<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('school:backup')->dailyAt('01:00');
