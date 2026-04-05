<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| FlamingDragon Scheduled Tasks
|--------------------------------------------------------------------------
*/
Schedule::command('fd:heartbeat')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
