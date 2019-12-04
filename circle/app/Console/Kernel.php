<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
      \App\Console\Commands\PushTask::class,
      \App\Console\Commands\CircleContentCheck::class,
      \App\Console\Commands\CircleDataStat::class,
      \App\Console\Commands\CirclePraise::class,
      \App\Console\Commands\CircleWeibo::class,
      \App\Console\Commands\CircleSettleAccount::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
      $schedule->command('push:task')
        ->timezone('Asia/Shanghai')
        ->everyMinute();
      //->everyFiveMinutes();

      $schedule->command('circle_content:check')
        ->timezone('Asia/Shanghai')
        ->everyMinute();
      //->everyFiveMinutes();

      $schedule->command('circle_data:stat')
        ->timezone('Asia/Shanghai')
        ->everyFiveMinutes();

      $schedule->command('content:praise')
        ->timezone('Asia/Shanghai')
        ->everyFiveMinutes();

      $schedule->command('content:weibo')
        ->timezone('Asia/Shanghai')
        ->everyFiveMinutes();

      $schedule->command('circle:settle')
        ->timezone('Asia/Shanghai')
        ->everyFiveMinutes();
    }
}
