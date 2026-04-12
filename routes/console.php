<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:fetch-light-data')->everyMinute();
Schedule::command('app:fetch-temperature-data')->everyMinute();
Schedule::command('app:evaluate-auto-relay')->everyMinute();
Schedule::command('app:check-sensor-anomalies')->everyFiveMinutes();
Schedule::command('app:send-daily-insight')->dailyAt('00:05');
