<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\SendInstallmentRemindersCommand;
use App\Console\Commands\EnforceSubscriptionBillingCommand;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command(SendInstallmentRemindersCommand::class)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(EnforceSubscriptionBillingCommand::class)
    ->everyMinute()
    ->withoutOverlapping();


// Schedule::command(SendInstallmentRemindersCommand::class)
//     ->dailyAt('09:00')
//     ->withoutOverlapping();

// Schedule::command(EnforceSubscriptionBillingCommand::class)
//     ->dailyAt('06:00')
//     ->withoutOverlapping();