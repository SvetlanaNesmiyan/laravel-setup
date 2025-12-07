<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueMonitor extends Command
{
    protected $signature = 'queue:monitor
                            {--stats : Показати статистику черг}
                            {--failed : Показати невдалі завдання}
                            {--retry : Повторити всі невдалі завдання}
                            {--clear : Очистити всі черги}
                            {--workers : Показати активних воркерів}';

    protected $description = 'Моніторинг та управління чергами';

    public function handle(): int
    {
        if ($this->option('stats')) {
            $this->showQueueStats();
        } elseif ($this->option('failed')) {
            $this->showFailedJobs();
        } elseif ($this->option('retry')) {
            $this->retryFailedJobs();
        } elseif ($this->option('clear')) {
            $this->clearQueues();
        } elseif ($this->option('workers')) {
            $this->showWorkers();
        } else {
            $this->showQueueStatus();
        }

        return Command::SUCCESS;
    }

    protected function showQueueStatus(): void
    {
        $jobsCount = DB::table('jobs')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();

        $this->info("=== СТАТУС ЧЕРГ ===");
        $this->line("Завдань у черзі: {$jobsCount}");
        $this->line("Невдалих завдань: {$failedJobsCount}");

        if ($jobsCount > 0) {
            $queues = DB::table('jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->get();

            $this->info("\nЧерги:");
            foreach ($queues as $queue) {
                $this->line("  {$queue->queue}: {$queue->count} завдань");
            }

            $this->table(
                ['ID', 'Черга', 'Спроби', 'Доступно з', 'Створено'],
                DB::table('jobs')
                    ->select('id', 'queue', 'attempts', 'available_at', 'created_at')
                    ->orderBy('available_at')
                    ->limit(10)
                    ->get()
                    ->map(function ($job) {
                        return [
                            $job->id,
                            $job->queue,
                            $job->attempts,
                            date('Y-m-d H:i:s', $job->available_at),
                            date('Y-m-d H:i:s', $job->created_at),
                        ];
                    })->toArray()
            );
        }
    }

    protected function showQueueStats(): void
    {
        $stats = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get();

        $this->info("=== СТАТИСТИКА ЗА ЧЕРГАМИ ===");

        if ($stats->isEmpty()) {
            $this->line("Черги порожні");
        } else {
            $this->table(['Черга', 'Кількість'], $stats->toArray());
        }

        $failedStats = DB::table('failed_jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get();

        if ($failedStats->isNotEmpty()) {
            $this->info("\n=== НЕВДАЛІ ЗАВДАННЯ ===");
            $this->table(['Черга', 'Кількість'], $failedStats->toArray());
        }

        $dailyStats = DB::table('jobs')
            ->select(DB::raw('DATE(FROM_UNIXTIME(created_at)) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(7)
            ->get();

        if ($dailyStats->isNotEmpty()) {
            $this->info("\n=== СТАТИСТИКА ЗА ОСТАННІ 7 ДНІВ ===");
            $this->table(['Дата', 'Кількість завдань'], $dailyStats->toArray());
        }
    }

    protected function showFailedJobs(): void
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(20)
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info("Немає невдалих завдань");
            return;
        }

        $this->info("=== ОСТАННІ НЕВДАЛІ ЗАВДАННЯ ===");

        $tableData = $failedJobs->map(function ($job) {
            $exception = json_decode($job->exception, true);
            $error = $exception['message'] ?? 'Невідома помилка';

            return [
                $job->id,
                $job->queue,
                substr($error, 0, 80) . (strlen($error) > 80 ? '...' : ''),
                $job->failed_at,
            ];
        })->toArray();

        $this->table(['ID', 'Черга', 'Помилка', 'Дата'], $tableData);
    }

    protected function retryFailedJobs(): void
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            $this->info("Немає невдалих завдань для повторної спроби");
            return;
        }

        if ($this->confirm("Повторити всі {$count} невдалі завдання?")) {
            $this->call('queue:retry', ['id' => ['all']]);
            $this->info("Всі завдання відправлені на повторне виконання.");
        }
    }

    protected function clearQueues(): void
    {
        $jobsCount = DB::table('jobs')->count();
        $failedCount = DB::table('failed_jobs')->count();

        $this->warn("=== ОЧИЩЕННЯ ЧЕРГ ===");
        $this->line("Завдань у черзі: {$jobsCount}");
        $this->line("Невдалих завдань: {$failedCount}");

        if ($jobsCount === 0 && $failedCount === 0) {
            $this->info("Черги вже порожні");
            return;
        }

        if ($this->confirm("Ви впевнені, що хочете очистити всі черги?")) {
            DB::table('jobs')->truncate();
            DB::table('failed_jobs')->truncate();

            $this->info("Всі черги очищено.");
        }
    }

    protected function showWorkers(): void
    {
        try {
            if (config('queue.default') === 'redis') {
                $workers = Redis::connection()->command('client', ['list']);
                $this->info("Активні з'єднання Redis:");
                $this->line($workers);
            } else {
                $this->info("Для перегляду воркерів використовується драйвер: " . config('queue.default'));
                $this->info("Для драйвера database воркери не відстежуються автоматично.");
            }
        } catch (\Exception $e) {
            $this->error("Помилка при отриманні інформації про воркери: " . $e->getMessage());
        }
    }
}
