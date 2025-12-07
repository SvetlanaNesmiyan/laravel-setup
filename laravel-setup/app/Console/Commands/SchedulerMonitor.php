<?php

namespace App\Console\Commands;

use App\Models\SchedulerLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SchedulerMonitor extends Command
{
    protected $signature = 'scheduler:monitor
                            {--stats : Показати статистику виконання}
                            {--failed : Показати невдалі виконання}
                            {--clean : Очистити старі логи}
                            {--commands : Показати всі заплановані команди}
                            {--hung : Показати "завислі" виконання}
                            {--fix-hung : Автоматично виправити "завислі" виконання}
                            {--days=7 : Кількість днів для статистики (за замовчуванням 7)}';

    protected $description = 'Моніторинг та управління планувальником задач Laravel';

    public function handle(): int
    {
        if ($this->option('stats')) {
            return $this->showStats();
        }

        if ($this->option('failed')) {
            return $this->showFailed();
        }

        if ($this->option('clean')) {
            return $this->cleanup();
        }

        if ($this->option('commands')) {
            return $this->showScheduledCommands();
        }

        if ($this->option('hung')) {
            return $this->showHungTasks();
        }

        if ($this->option('fix-hung')) {
            return $this->fixHungTasks();
        }

        return $this->showStatus();
    }

    protected function showStatus(): int
    {
        $this->newLine();
        $this->info('📊 МОНІТОРИНГ ПЛАНУВАЛЬНИКА');
        $this->line(str_repeat('═', 50));

        $today = Carbon::today();

        $statsToday = [
            'Запущено команд' => SchedulerLog::whereDate('started_at', $today)->count(),
            'Успішних' => SchedulerLog::whereDate('started_at', $today)
                ->where('status', 'completed')->count(),
            'Невдалих' => SchedulerLog::whereDate('started_at', $today)
                ->where('status', 'failed')->count(),
            'В процесі' => SchedulerLog::where('status', 'running')
                ->where('started_at', '>', now()->subHours(1))
                ->count(),
        ];

        $this->info('📈 Статистика за сьогодні:');
        foreach ($statsToday as $label => $value) {
            $color = match($label) {
                'Невдалих' => $value > 0 ? 'red' : 'green',
                'В процесі' => $value > 0 ? 'yellow' : 'green',
                default => 'white',
            };
            $this->line("  <fg={$color}>{$label}:</> {$value}");
        }

        $this->newLine();
        $this->info('⏰ Останні виконання:');

        $logs = SchedulerLog::with('user')
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get();

        if ($logs->isEmpty()) {
            $this->line('  Немає логів');
        } else {
            $this->table(
                ['Команда', 'Статус', 'Початок', 'Час', 'Користувач'],
                $logs->map(function ($log) {
                    [$statusIcon, $statusColor] = match($log->status) {
                        'completed' => ['✅', 'green'],
                        'failed' => ['❌', 'red'],
                        'running' => ['🔄', 'yellow'],
                        default => ['⏳', 'gray'],
                    };

                    $startTime = $log->started_at?->format('H:i:s') ?? '—';
                    $executionTime = $log->execution_time ? $this->formatExecutionTime($log->execution_time) : '—';
                    $user = $log->user ? $log->user->name : 'Система';

                    return [
                        "<fg=cyan>{$log->command}</>",
                        "<fg={$statusColor}>{$statusIcon} {$log->status}</>",
                        $startTime,
                        $executionTime,
                        $user,
                    ];
                })
            );
        }

        $hungTasks = $this->getHungTasks();
        if ($hungTasks->count() > 0) {
            $this->newLine();
            $this->warn('⚠️  Виявлено завислих задач: ' . $hungTasks->count());
            $this->line('   Використайте <fg=yellow>--hung</> для деталей або <fg=yellow>--fix-hung</> для автоматичного виправлення');
        }

        $this->newLine();
        $this->info('📅 Наступні заплановані запуски:');
        $this->call('schedule:list');

        $this->newLine();
        $this->line('💡 Довідка:');
        $this->line('  <fg=yellow>--stats</>    - детальна статистика');
        $this->line('  <fg=yellow>--failed</>   - невдалі виконання');
        $this->line('  <fg=yellow>--hung</>     - завислі задачі');
        $this->line('  <fg=yellow>--clean</>    - очищення старих логів');
        $this->line('  <fg=yellow>--commands</> - список запланованих команд');

        return Command::SUCCESS;
    }

    protected function showStats(): int
    {
        $days = (int)$this->option('days');
        $this->newLine();
        $this->info("📊 СТАТИСТИКА ВИКОНАННЯ КОМАНД (за останні {$days} днів)");
        $this->line(str_repeat('═', 70));

        $logs = SchedulerLog::with('user')
            ->where('started_at', '>', now()->subDays($days))
            ->get()
            ->groupBy('command');

        if ($logs->isEmpty()) {
            $this->info('ℹ️  Немає даних за вказаний період');
            return Command::SUCCESS;
        }

        $tableData = [];
        $totalCompleted = 0;
        $totalFailed = 0;
        $totalTasks = 0;

        foreach ($logs as $command => $commandLogs) {
            $completed = $commandLogs->where('status', 'completed')->count();
            $failed = $commandLogs->where('status', 'failed')->count();
            $total = $commandLogs->count();
            $successRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

            $avgTime = $commandLogs->where('status', 'completed')
                ->filter(fn($log) => $log->execution_time > 0)
                ->avg('execution_time');

            $lastRun = $commandLogs->max('started_at');
            $lastRunFormatted = $lastRun ? $lastRun->format('d.m.Y H:i') : '—';

            $successColor = match(true) {
                $successRate >= 95 => 'green',
                $successRate >= 80 => 'yellow',
                default => 'red',
            };

            $tableData[] = [
                "<fg=cyan>{$command}</>",
                $total,
                "<fg=green>{$completed}</>",
                $failed > 0 ? "<fg=red>{$failed}</>" : "{$failed}",
                "<fg={$successColor}>{$successRate}%</>",
                $avgTime ? "<fg=blue>" . round($avgTime) . 'мс</>' : '—',
                $lastRunFormatted,
            ];

            $totalCompleted += $completed;
            $totalFailed += $failed;
            $totalTasks += $total;
        }

        usort($tableData, fn($a, $b) => intval($b[1]) - intval($a[1]));

        $this->table(
            ['Команда', 'Всього', 'Успішно', 'Невдало', 'Успішність', 'Середній час', 'Останній запуск'],
            $tableData
        );

        // Загальна статистика
        $this->newLine();
        $this->info('📈 ЗАГАЛЬНА СТАТИСТИКА:');
        $totalSuccessRate = $totalTasks > 0 ? round(($totalCompleted / $totalTasks) * 100, 2) : 0;

        $this->table(
            ['Показник', 'Значення'],
            [
                ['Загальна кількість виконань', "<fg=cyan>{$totalTasks}</>"],
                ['Успішних виконань', "<fg=green>{$totalCompleted}</>"],
                ['Невдалих виконань', $totalFailed > 0 ? "<fg=red>{$totalFailed}</>" : "{$totalFailed}"],
                ['Загальна успішність', $this->getSuccessRateColor($totalSuccessRate)],
                ['Період аналізу', "{$days} днів"],
            ]
        );

        return Command::SUCCESS;
    }

    protected function showFailed(): int
    {
        $failedLogs = SchedulerLog::with('user')
            ->where('status', 'failed')
            ->orderBy('started_at', 'desc')
            ->limit(20)
            ->get();

        if ($failedLogs->isEmpty()) {
            $this->info('✅ Немає невдалих виконань');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('❌ ОСТАННІ НЕВДАЛІ ВИКОНАННЯ');
        $this->line(str_repeat('═', 100));

        $tableData = $failedLogs->map(function ($log) {
            $errorMessage = $log->error_message ?? 'Немає повідомлення';
            $truncatedError = strlen($errorMessage) > 100
                ? substr($errorMessage, 0, 100) . '...'
                : $errorMessage;

            return [
                "<fg=cyan>{$log->command}</>",
                $log->started_at?->format('d.m.Y H:i:s') ?? '—',
                "<fg=red>{$truncatedError}</>",
                $log->execution_time ? $this->formatExecutionTime($log->execution_time) : '—',
                $log->user ? $log->user->name : 'Система',
            ];
        });

        $this->table(
            ['Команда', 'Час запуску', 'Помилка', 'Час виконання', 'Користувач'],
            $tableData
        );

        $this->newLine();
        $this->info('📊 АНАЛІЗ ПОМИЛОК:');

        $errorStats = $failedLogs->groupBy('command')
            ->map(function ($logs, $command) {
                return [
                    'command' => $command,
                    'count' => $logs->count(),
                    'last_error' => $logs->first()->error_message,
                    'last_time' => $logs->first()->started_at->format('d.m.Y H:i'),
                ];
            })
            ->sortByDesc('count');

        $this->table(
            ['Команда', 'Кількість помилок', 'Остання помилка', 'Останній раз'],
            $errorStats->map(function ($stat) {
                $error = strlen($stat['last_error']) > 50
                    ? substr($stat['last_error'], 0, 50) . '...'
                    : $stat['last_error'];

                return [
                    "<fg=cyan>{$stat['command']}</>",
                    $stat['count'] > 1 ? "<fg=red>{$stat['count']}</>" : $stat['count'],
                    $error,
                    $stat['last_time'],
                ];
            })
        );

        return Command::SUCCESS;
    }

    protected function cleanup(): int
    {
        if (!$this->confirm('❓ Ви впевнені, що хочете видалити логи старше 30 днів?', false)) {
            $this->info('Операція скасована');
            return Command::SUCCESS;
        }

        try {
            $deleted = SchedulerLog::cleanup(30);
            $this->info("✅ Видалено {$deleted} старих логів (старше 30 днів)");

            Cache::tags(['scheduler'])->flush();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Помилка при очищенні логів: ' . $e->getMessage());
            Log::error('Помилка очищення логів планувальника', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    protected function showScheduledCommands(): int
    {
        $this->call('schedule:list', ['--next' => true]);
        return Command::SUCCESS;
    }

    protected function showHungTasks(): int
    {
        $hungTasks = $this->getHungTasks();

        if ($hungTasks->isEmpty()) {
            $this->info('✅ Немає завислих задач');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('⚠️  ЗАВИСЛІ ЗАДАЧІ (старше 1 години)');
        $this->line(str_repeat('═', 80));

        $this->table(
            ['Команда', 'Початок', 'Тривалість', 'Користувач', 'Дані'],
            $hungTasks->map(function ($log) {
                $startedAt = $log->started_at;
                $duration = $startedAt ? now()->diffInHours($startedAt) . ' год. ' . now()->diffInMinutes($startedAt) % 60 . ' хв.' : '—';

                $inputData = $log->input_data
                    ? json_encode($log->input_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    : '—';

                $truncatedData = strlen($inputData) > 50
                    ? substr($inputData, 0, 50) . '...'
                    : $inputData;

                return [
                    "<fg=red>{$log->command}</>",
                    $startedAt?->format('d.m.Y H:i:s') ?? '—',
                    "<fg=yellow>{$duration}</>",
                    $log->user ? $log->user->name : 'Система',
                    $truncatedData,
                ];
            })
        );

        $this->newLine();
        $this->warn('💡 Для автоматичного виправлення виконайте:');
        $this->line('  <fg=yellow>php artisan scheduler:monitor --fix-hung</>');

        return Command::SUCCESS;
    }

    protected function fixHungTasks(): int
    {
        $hungTasks = $this->getHungTasks();

        if ($hungTasks->isEmpty()) {
            $this->info('✅ Немає завислих задач для виправлення');
            return Command::SUCCESS;
        }

        $this->info("🔧 Виявлено {$hungTasks->count()} завислих задач для виправлення");

        if (!$this->confirm('❓ Ви впевнені, що хочете позначити ці задачі як невдалі?', false)) {
            $this->info('Операція скасована');
            return Command::SUCCESS;
        }

        $fixedCount = 0;

        foreach ($hungTasks as $log) {
            try {
                $log->update([
                    'status' => 'failed',
                    'error_message' => 'Зависла задача (автоматично виправлено)',
                    'completed_at' => now(),
                ]);

                $this->line("✅ Виправлено: {$log->command} (стартовав: {$log->started_at->format('H:i:s')})");
                $fixedCount++;

                Log::warning('Виправлено завислу задачу планувальника', [
                    'log_id' => $log->id,
                    'command' => $log->command,
                    'started_at' => $log->started_at,
                ]);

            } catch (\Exception $e) {
                $this->error("❌ Помилка при виправленні задачі {$log->id}: " . $e->getMessage());
            }
        }

        $this->info("\n🎯 Виправлено {$fixedCount} з {$hungTasks->count()} завислих задач");

        return Command::SUCCESS;
    }

    private function getHungTasks()
    {
        return SchedulerLog::where('status', 'running')
            ->where('started_at', '<', now()->subHour())
            ->orderBy('started_at', 'asc')
            ->get();
    }

    private function formatExecutionTime(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return "<fg=green>{$milliseconds}мс</>";
        } elseif ($milliseconds < 10000) {
            $seconds = round($milliseconds / 1000, 1);
            return "<fg=yellow>{$seconds}с</>";
        } else {
            $seconds = round($milliseconds / 1000, 1);
            return "<fg=red>{$seconds}с</>";
        }
    }

    private function getSuccessRateColor(float $rate): string
    {
        if ($rate >= 95) {
            return "<fg=green>{$rate}%</> 🏆";
        } elseif ($rate >= 80) {
            return "<fg=yellow>{$rate}%</> ⚠️";
        } else {
            return "<fg=red>{$rate}%</> ❌";
        }
    }
}
