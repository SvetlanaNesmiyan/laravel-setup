<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:check-overdue-tasks')
            ->dailyAt('08:00')
            ->timezone('Europe/Kiev')
            ->before(function () {
                Log::info('🔄 Початок щоденної перевірки прострочених задач');
            })
            ->after(function () {
                Log::info('✅ Щоденна перевірка прострочених задач завершена');
            })
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        $schedule->command('app:generate-report --period=7 --file')
            ->weeklyOn(1, '09:00') // Понеділок, 9:00
            ->timezone('Europe/Kiev')
            ->before(function () {
                Log::info('📊 Початок щотижневої генерації звіту');
            })
            ->after(function () {
                Log::info('✅ Щотижнева генерація звіту завершена');
            })
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        $schedule->call(function () {
            \App\Models\SchedulerLog::cleanup(30);
            Log::info('🧹 Очищено старі логи планувальника');
        })->monthlyOn(1, '00:00')
        ->timezone('Europe/Kiev');

        $schedule->call(function () {
            $cutoffDate = now()->subMonths(3);
            $deleted = \App\Models\Report::where('created_at', '<', $cutoffDate)->delete();
            Log::info("🧹 Видалено {$deleted} старих звітів");
        })->monthlyOn(15, '01:00')
        ->timezone('Europe/Kiev');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     */
    protected function scheduleTimezone(): string
    {
        return 'Europe/Kiev'; // Або ваш часовий пояс
    }
}
