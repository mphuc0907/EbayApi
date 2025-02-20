<?php

use App\Jobs\UpdatePromotionStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

//Schedule::job(new UpdatePromotionStatus)->everyMinute();


Schedule::command('app:update-promotion')->everyMinute();
Schedule::command('orders:transfer-money')->everyMinute();
Schedule::command('report:auto-refund')->everyMinute();



