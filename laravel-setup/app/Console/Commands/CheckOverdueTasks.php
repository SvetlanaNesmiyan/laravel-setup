<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\SchedulerLog;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueTasks extends Command
{
    protected $signature = 'app:check-overdue-tasks
                            {--force : Примусово виконати перевірку, незалежно від часу останнього запуску}
                            {--dry-run : Тільки показати задачі, не оновлювати статус}';

    protected $description = 'Перевіряє задачі в статусі in_progress понад 7 днів і позначає їх як прострочені';

    private TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $log = null;

        try {
            if (!$this->option('force') && SchedulerLog::isRunning($this->signature)) {
                $this->warn('Команда вже виконується!');
                return Command::FAILURE;
            }

            $log = SchedulerLog::start(
                $this->signature,
                'Перевірка прострочених задач',
                $this->arguments()
            );

            $this->info('🔍 Початок перевірки прострочених задач...');

            $cutoffDate = Carbon::now()->subDays(7);

            $query = Task::where('status', 'in_progress')
                ->where('created_at', '<=', $cutoffDate);

            $tasks = $query->with(['project', 'author', 'assignee'])->get();

            $this->info("📊 Знайдено задач для перевірки: " . $tasks->count());

            if ($tasks->isEmpty()) {
                $this->info('✅ Немає прострочених задач');

                $log->complete([
                    'message' => 'Немає прострочених задач',
                    'tasks_count' => 0,
                    'updated_count' => 0,
                ], (int)((microtime(true) - $startTime) * 1000));

                return Command::SUCCESS;
            }

            $updatedCount = 0;
            $dryRun = $this->option('dry-run');

            foreach ($tasks as $task) {
                $daysInProgress = $task->created_at->diffInDays();

                $this->line("🔸 Задача #{$task->id}: '{$task->title}'");
                $this->line("   Статус: in_progress протягом {$daysInProgress} днів");
                $this->line("   Автор: {$task->author->name}");
                $this->line("   Виконавець: {$task->assignee->name}");
                $this->line("   Проєкт: {$task->project->name}");

                if (!$dryRun) {
                    $task->update([
                        'status' => 'expired',
                        'expired_at' => now(),
                    ]);

                    $updatedCount++;

                    $this->sendExpiredNotification($task);

                    $this->info("   ✅ Позначено як прострочена");
                } else {
                    $this->info("   👁️  Буде позначено як прострочена (dry-run)");
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("\n📊 ПІДСУМОК:");
            $this->line("Перевірено задач: " . $tasks->count());
            $this->line("Позначено простроченими: " . $updatedCount);
            $this->line("Час виконання: {$executionTime}мс");

            $log->complete([
                'message' => 'Перевірка прострочених задач завершена',
                'tasks_count' => $tasks->count(),
                'updated_count' => $updatedCount,
                'dry_run' => $dryRun,
                'execution_time_ms' => $executionTime,
            ], (int)$executionTime);

            if ($dryRun) {
                $this->info("\nℹ️  Режим dry-run: жодних змін не внесено");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $errorMessage = "Помилка при перевірці прострочених задач: " . $e->getMessage();
            $this->error($errorMessage);
            Log::error($errorMessage, ['exception' => $e]);

            if ($log) {
                $log->fail($errorMessage);
            }

            return Command::FAILURE;
        }
    }

    private function sendExpiredNotification(Task $task): void
    {
        try {
            $chatId = config('services.telegram.chat_id');

            if (!$chatId) {
                $this->warn("   ⚠️  Telegram Chat ID не налаштовано, сповіщення не надіслано");
                return;
            }

            $daysInProgress = $task->created_at->diffInDays();

            $message = "⚠️ <b>Задача прострочена</b>\n\n"
                . "📝 <b>Задача:</b> {$task->title}\n"
                . "🆔 <b>ID:</b> <code>{$task->id}</code>\n"
                . "📂 <b>Проєкт:</b> {$task->project->name}\n"
                . "👤 <b>Автор:</b> {$task->author->name}\n"
                . "🎯 <b>Виконавець:</b> {$task->assignee->name}\n"
                . "⏳ <b>В статусі in_progress:</b> {$daysInProgress} днів\n"
                . "📅 <b>Створена:</b> {$task->created_at->format('d.m.Y')}\n"
                . "🚫 <b>Статус:</b> Прострочено\n\n"
                . "Задача автоматично переведена в статус 'Прострочено' через тривалий час без оновлень.";

            $result = $this->telegramService->sendMessage($chatId, $message);

            if ($result['ok'] ?? false) {
                $this->info("   📨 Сповіщення надіслано в Telegram");
            } else {
                $this->warn("   ⚠️  Не вдалося надіслати сповіщення в Telegram");
            }

        } catch (\Exception $e) {
            $this->warn("   ⚠️  Помилка при відправці Telegram сповіщення: " . $e->getMessage());
            Log::warning('Помилка при відправці Telegram сповіщення', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getStats(): array
    {
        $cutoffDate = Carbon::now()->subDays(7);

        $expiredCount = Task::where('status', 'expired')->count();
        $aboutToExpire = Task::where('status', 'in_progress')
            ->where('created_at', '<=', $cutoffDate)
            ->count();
        $totalInProgress = Task::where('status', 'in_progress')->count();

        return [
            'expired_tasks' => $expiredCount,
            'about_to_expire' => $aboutToExpire,
            'total_in_progress' => $totalInProgress,
            'expired_percentage' => $totalInProgress > 0
                ? round(($expiredCount / $totalInProgress) * 100, 2)
                : 0,
        ];
    }
}
