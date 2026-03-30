<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:fetch-light-data')->everyMinute();
Schedule::command('app:fetch-temperature-data')->everyMinute();
