<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
  /**
   * The Artisan commands provided by your application.
   *
   * @var array
   */
  protected $commands = [
    //
  ];

  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   * @return void
   */
  protected function schedule(Schedule $schedule)
  {
    // Resale marketplace time-based tasks: expire reservations, enforce missed
    // transfer deadlines (relist + deprioritize, or cancel on 2nd miss),
    // release due payments, refresh confidence levels, sync deprioritization
    // flags, and execute due withdrawals. Reuses the same controller logic as
    // the public /resale/cron endpoint.
    $schedule->call(function () {
      app()->call('App\Http\Controllers\CronJobController@resaleTick');
    })->everyMinute()->withoutOverlapping()->name('resale-tick');
  }

  /**
   * Register the commands for the application.
   *
   * @return void
   */
  protected function commands()
  {
    $this->load(__DIR__ . '/Commands');

    require base_path('routes/console.php');
  }
}
