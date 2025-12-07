<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueCleanup extends Command
{
    protected $signature = 'queue:cleanup
                            {--old : Видалити старі завдання (старше 7 днів)}
                            {--failed : Очистити невдалі завдання}
                            {--expired : Видалити прострочені завдання (доступні понад 30 днів тому)}';

    protected $description = 'Очищення черг';

    public function handle(): int
    {
        if ($this->option('old')) {
            $this->cleanOldJobs();
        } elseif ($this->option('failed')) {
            $this->cleanFailedJobs();
        } elseif ($this->option('expired')) {
            $this->cleanExpiredJobs();
        } else {
            $this->cleanAll();
        }

        return Command::SUCCESS;
    }

    protected function cleanOldJobs(): void
    {
        $deleted = DB::table('jobs')
            ->where('created_at', '<', now()->subDays(7))
            ->delete();

        $this->info("Видалено {$deleted} старих завдань.");
    }

    protected function cleanFailedJobs(): void
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            $this->info("Немає невдалих завдань для очищення.");
            return;
        }

        if ($this->confirm("Видалити {$count} невдалих завдань?")) {
            DB::table('failed_jobs')->truncate();
            $this->info("Всі невдалі завдання видалено.");
        }
    }

    protected function cleanExpiredJobs(): void
    {
        $deleted = DB::table('jobs')
            ->where('available_at', '<', now()->subDays(30)->timestamp)
            ->delete();

        $this->info("Видалено {$deleted} прострочених завдань.");
    }

    protected function cleanAll(): void
    {
        $jobsCount = DB::table('jobs')->count();
        $failedCount = DB::table('failed_jobs')->count();

        $this->warn("=== ПОТОЧНИЙ СТАН ЧЕРГ ===");
        $this->line("Завдань у черзі: {$jobsCount}");
        $this->line("Невдалих завдань: {$failedCount}");

        $options = [
            'old' => 'Видалити старі завдання (старше 7 днів)',
            'failed' => 'Очистити невдалі завдання',
            'expired' => 'Видалити прострочені завдання',
            'all' => 'Очистити ВСІ черги (небезпечно!)',
            'cancel' => 'Скасування',
        ];

        $choice = $this->choice('Виберіть дію:', $options);

        switch ($choice) {
            case 'old':
                $this->cleanOldJobs();
                break;
            case 'failed':
                $this->cleanFailedJobs();
                break;
            case 'expired':
                $this->cleanExpiredJobs();
                break;
            case 'all':
                if ($this->confirm("Ви впевнені, що хочете очистити ВСІ черги? Цю дію не можна скасувати.")) {
                    DB::table('jobs')->truncate();
                    DB::table('failed_jobs')->truncate();
                    $this->info("Всі черги очищено.");
                }
                break;
            default:
                $this->info("Дію скасовано.");
        }
    }
}
