<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:queue-reminders')->hourly()->withoutOverlapping();
Schedule::command('care:queue-reminders')->everyMinute()->withoutOverlapping();

Schedule::command('patients:queue-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('voice:purge')->hourly()->withoutOverlapping();
