<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Report;
use App\Models\SchedulerLog;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateReport extends Command
{
    protected $signature = 'app:generate-report
                            {--project= : ID проєкту (якщо не вказано - для всіх проєктів)}
                            {--period= : Період у днях (за замовчуванням 30)}
                            {--file : Зберегти звіт у файл}
                            {--email= : Email для відправлення звіту (опціонально)}
                            {--force : Примусово запустити команду, навіть якщо вона вже виконується}
                            {--format=json : Формат файлу (json, csv, txt)}
                            {--storage=local : Диск для зберігання (local, s3, reports)}
                            {--silent : Мінімальний вивід в консоль}';

    protected $description = 'Генерує звіт за задачами у проєктах з логуванням виконання';

    public function handle(): int
    {
        $startTime = microtime(true);
        $log = null;

        try {
            if (!$this->option('force') && SchedulerLog::isRunning($this->signature)) {
                $this->warn('Команда вже виконується! Використовуйте --force для примусового запуску.');
                return Command::FAILURE;
            }

            $log = SchedulerLog::start(
                $this->signature,
                'Генерація звіту за задачами',
                [
                    'project_id' => $this->option('project'),
                    'period_days' => $this->option('period'),
                    'save_to_file' => $this->option('file'),
                    'email' => $this->option('email'),
                    'format' => $this->option('format'),
                ]
            );

            $this->info("🚀 Запуск генерації звіту [ID логу: {$log->id}]");

            $projectId = $this->option('project');
            $periodDays = (int)$this->option('period') ?: 30;
            $saveToFile = $this->option('file');
            $email = $this->option('email');
            $format = $this->option('format');
            $storageDisk = $this->option('storage');
            $silentMode = $this->option('silent');

            $periodStart = Carbon::now()->subDays($periodDays);
            $periodEnd = Carbon::now();

            if (!$silentMode) {
                $this->info("📅 Період звіту: {$periodStart->toDateString()} - {$periodEnd->toDateString()}");
                if ($projectId) {
                    $this->info("🎯 Фокус на проєкті ID: {$projectId}");
                }
            }

            $query = Project::query();

            if ($projectId) {
                $query->where('id', $projectId);
            }

            $projects = $query->with(['tasks' => function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->with(['author', 'assignee']);
            }, 'owner', 'users'])->get();

            if ($projects->isEmpty()) {
                $errorMessage = $projectId
                    ? "Проєкт з ID {$projectId} не знайдено!"
                    : "Проєкти не знайдені!";

                $this->error($errorMessage);
                $log->fail($errorMessage);
                return Command::FAILURE;
            }

            $reportData = $this->generateReportData($projects, $periodStart, $periodEnd);

            $log->updateProgress([
                'projects_count' => $projects->count(),
                'total_tasks' => $reportData['summary']['total_tasks'],
                'status' => 'processing_data',
            ]);

            $report = Report::create([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'project_id' => $projectId,
                'payload' => $reportData,
                'path' => null,
                'format' => $format,
                'generated_by' => 'command',
                'scheduler_log_id' => $log->id,
            ]);

            if (!$silentMode) {
                $this->info("✅ Звіт збережено в базу даних. ID: {$report->id}");
            }

            $filePath = null;
            $fullFilePath = null;

            if ($saveToFile) {
                $fileData = $this->saveReportToFile($report, $reportData, $format, $storageDisk);
                $filePath = $fileData['path'];
                $fullFilePath = $fileData['full_path'];

                $report->update(['path' => $filePath]);

                if (!$silentMode) {
                    $this->info("💾 Файл звіту збережено: {$filePath}");
                    if ($fullFilePath && file_exists($fullFilePath)) {
                        $fileSize = round(filesize($fullFilePath) / 1024, 2);
                        $this->info("📦 Розмір файлу: {$fileSize} KB");
                    }
                }
            }

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emailSent = $this->sendEmailReport($email, $reportData, $filePath);
                if ($emailSent && !$silentMode) {
                    $this->info("📧 Звіт надіслано на email: {$email}");
                }
            }

            if (!$silentMode) {
                $this->displaySummaryTable($reportData);
                $this->displayFinalSummary($reportData, $report);
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            $executionTimeMs = round($executionTime * 1000, 2);

            if (!$silentMode) {
                $this->info("⏱ Час виконання: {$executionTime} секунд ({$executionTimeMs} мс)");
            }

            $log->complete([
                'message' => 'Звіт успішно згенеровано',
                'report_id' => $report->id,
                'file_path' => $filePath,
                'projects_count' => $projects->count(),
                'total_tasks' => $reportData['summary']['total_tasks'],
                'execution_time_seconds' => $executionTime,
                'execution_time_ms' => $executionTimeMs,
                'report_size_kb' => $fullFilePath && file_exists($fullFilePath)
                    ? round(filesize($fullFilePath) / 1024, 2)
                    : null,
            ], (int)$executionTimeMs);

            Log::info('Звіт успішно згенеровано', [
                'report_id' => $report->id,
                'scheduler_log_id' => $log->id,
                'projects_count' => $projects->count(),
                'total_tasks' => $reportData['summary']['total_tasks'],
                'execution_time' => $executionTime,
                'file_path' => $filePath,
            ]);

            if (!$silentMode) {
                $this->newLine();
                $this->line('=' . str_repeat('=', 50));
                $this->info('🎉 ГЕНЕРАЦІЯ ЗВІТУ УСПІШНО ЗАВЕРШЕНА!');
                $this->line('=' . str_repeat('=', 50));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $errorMessage = "Помилка при генерації звіту: " . $e->getMessage();

            if (!$this->option('silent')) {
                $this->error($errorMessage);
                $this->error("Трасування: " . $e->getTraceAsString());
            }

            Log::error($errorMessage, [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'command' => $this->signature,
                'options' => $this->options(),
            ]);

            if ($log) {
                $log->fail($errorMessage, [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return Command::FAILURE;
        }
    }

    private function generateReportData($projects, Carbon $periodStart, Carbon $periodEnd): array
    {
        $reportData = [
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'total_projects' => $projects->count(),
                'generator' => 'app:generate-report',
                'version' => '1.0',
            ],
            'projects' => [],
            'summary' => [
                'total_tasks' => 0,
                'total_expired' => 0,
                'total_members' => 0,
                'status_distribution' => [
                    'todo' => 0,
                    'in_progress' => 0,
                    'done' => 0,
                ],
                'priority_distribution' => [
                    'low' => 0,
                    'medium' => 0,
                    'high' => 0,
                ],
                'avg_completion_days' => 0,
            ],
            'statistics' => [
                'projects_with_expired_tasks' => 0,
                'projects_without_tasks' => 0,
                'most_active_project' => null,
                'most_tasks_assignee' => null,
            ]
        ];

        $totalCompletionDays = 0;
        $projectsWithCompletionData = 0;
        $assigneeTaskCounts = [];

        foreach ($projects as $project) {
            $tasks = $project->tasks;

            $statusCounts = [
                'todo' => $tasks->where('status', 'todo')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'done' => $tasks->where('status', 'done')->count(),
            ];

            $expiredTasks = $tasks->filter(function ($task) {
                return $task->due_date < now() && $task->status !== 'done';
            });

            $expiredCount = $expiredTasks->count();

            $priorityCounts = [
                'low' => $tasks->where('priority', 'low')->count(),
                'medium' => $tasks->where('priority', 'medium')->count(),
                'high' => $tasks->where('priority', 'high')->count(),
            ];

            $completedTasks = $tasks->where('status', 'done');
            $avgCompletionTime = 0;

            if ($completedTasks->isNotEmpty()) {
                $projectCompletionTime = $completedTasks->avg(function ($task) {
                    return $task->updated_at->diffInDays($task->created_at);
                });
                $avgCompletionTime = round($projectCompletionTime, 2);
                $totalCompletionDays += $projectCompletionTime;
                $projectsWithCompletionData++;
            }

            $assignees = $tasks->groupBy('assignee_id')
                ->map(function ($tasks, $assigneeId) use (&$assigneeTaskCounts) {
                    $firstTask = $tasks->first();
                    $taskCount = $tasks->count();

                    if ($firstTask->assignee) {
                        $assigneeTaskCounts[$firstTask->assignee->id] = [
                            'name' => $firstTask->assignee->name,
                            'count' => ($assigneeTaskCounts[$firstTask->assignee->id]['count'] ?? 0) + $taskCount,
                        ];
                    }

                    return [
                        'user_id' => $assigneeId,
                        'user_name' => $firstTask->assignee->name ?? 'Невідомо',
                        'task_count' => $taskCount,
                        'completed_count' => $tasks->where('status', 'done')->count(),
                    ];
                })->values();

            $projectData = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'description' => $project->description,
                'owner_id' => $project->owner_id,
                'owner_name' => $project->owner->name ?? 'Невідомо',
                'status' => $project->status,
                'total_tasks' => $tasks->count(),
                'status_counts' => $statusCounts,
                'expired_tasks' => $expiredCount,
                'priority_counts' => $priorityCounts,
                'avg_completion_days' => $avgCompletionTime,
                'members_count' => $project->users->count(),
                'assignees' => $assignees,
                'expired_task_details' => $expiredTasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'assignee' => $task->assignee->name ?? 'Невідомо',
                        'due_date' => $task->due_date->toDateString(),
                        'days_late' => now()->diffInDays($task->due_date),
                        'priority' => $task->priority,
                        'status' => $task->status,
                    ];
                })->values(),
                'created_at' => now()->toDateTimeString(),
            ];

            $reportData['projects'][$project->id] = $projectData;

            $reportData['summary']['total_tasks'] += $tasks->count();
            $reportData['summary']['total_expired'] += $expiredCount;
            $reportData['summary']['total_members'] += $project->users->count();

            $reportData['summary']['status_distribution']['todo'] += $statusCounts['todo'];
            $reportData['summary']['status_distribution']['in_progress'] += $statusCounts['in_progress'];
            $reportData['summary']['status_distribution']['done'] += $statusCounts['done'];

            $reportData['summary']['priority_distribution']['low'] += $priorityCounts['low'];
            $reportData['summary']['priority_distribution']['medium'] += $priorityCounts['medium'];
            $reportData['summary']['priority_distribution']['high'] += $priorityCounts['high'];

            if ($expiredCount > 0) {
                $reportData['statistics']['projects_with_expired_tasks']++;
            }

            if ($tasks->count() === 0) {
                $reportData['statistics']['projects_without_tasks']++;
            }
        }

        if ($projectsWithCompletionData > 0) {
            $reportData['summary']['avg_completion_days'] = round($totalCompletionDays / $projectsWithCompletionData, 2);
        }

        if (!empty($reportData['projects'])) {
            $mostTasks = 0;
            $mostTasksAssignee = null;

            foreach ($assigneeTaskCounts as $assigneeId => $data) {
                if ($data['count'] > $mostTasks) {
                    $mostTasks = $data['count'];
                    $mostTasksAssignee = $data['name'];
                }
            }

            $reportData['statistics']['most_active_project'] = collect($reportData['projects'])
                ->sortByDesc('total_tasks')
                ->first();
            $reportData['statistics']['most_tasks_assignee'] = $mostTasksAssignee;
        }

        return $reportData;
    }

    private function saveReportToFile(Report $report, array $reportData, string $format, string $storageDisk): array
    {
        $fileName = "report_{$report->period_start->format('Y-m-d')}_to_{$report->period_end->format('Y-m-d')}_" .
            ($report->project_id ? "project_{$report->project_id}_" : "") .
            "{$report->id}.{$format}";

        $directory = "reports/" . now()->format('Y/m/d');
        $filePath = "{$directory}/{$fileName}";

        Storage::disk($storageDisk)->makeDirectory($directory);

        $content = match($format) {
            'csv' => $this->convertToCsv($reportData),
            'txt' => $this->convertToText($reportData),
            default => json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        };

        Storage::disk($storageDisk)->put($filePath, $content);

        return [
            'path' => $filePath,
            'full_path' => Storage::disk($storageDisk)->path($filePath),
            'size' => Storage::disk($storageDisk)->size($filePath),
            'disk' => $storageDisk,
        ];
    }

    private function convertToCsv(array $data): string
    {
        $csvContent = "Звіт по проєктах\n";
        $csvContent .= "Період: {$data['meta']['period_start']} - {$data['meta']['period_end']}\n";
        $csvContent .= "Згенеровано: {$data['meta']['generated_at']}\n\n";

        $csvContent .= "Проєкт,Задач всього,To Do,В процесі,Виконано,Прострочено,Учасників\n";

        foreach ($data['projects'] as $project) {
            $csvContent .= "\"{$project['project_name']}\",";
            $csvContent .= "{$project['total_tasks']},";
            $csvContent .= "{$project['status_counts']['todo']},";
            $csvContent .= "{$project['status_counts']['in_progress']},";
            $csvContent .= "{$project['status_counts']['done']},";
            $csvContent .= "{$project['expired_tasks']},";
            $csvContent .= "{$project['members_count']}\n";
        }

        return $csvContent;
    }

    private function convertToText(array $data): string
    {
        $text = "=" . str_repeat("=", 60) . "\n";
        $text .= "ЗВІТ ПО ПРОЄКТАХ\n";
        $text .= "=" . str_repeat("=", 60) . "\n\n";

        $text .= "Період: {$data['meta']['period_start']} - {$data['meta']['period_end']}\n";
        $text .= "Згенеровано: {$data['meta']['generated_at']}\n";
        $text .= "Всього проєктів: {$data['meta']['total_projects']}\n\n";

        $text .= str_repeat("-", 60) . "\n";
        $text .= "ПІДСУМОК:\n";
        $text .= str_repeat("-", 60) . "\n";
        $text .= "Задач всього: {$data['summary']['total_tasks']}\n";
        $text .= "Прострочених задач: {$data['summary']['total_expired']}\n";
        $text .= "Учасників всього: {$data['summary']['total_members']}\n";
        $text .= "Середній час виконання: {$data['summary']['avg_completion_days']} днів\n\n";

        foreach ($data['projects'] as $project) {
            $text .= str_repeat("-", 60) . "\n";
            $text .= "ПРОЄКТ: {$project['project_name']}\n";
            $text .= str_repeat("-", 60) . "\n";
            $text .= "Задач: {$project['total_tasks']} (To Do: {$project['status_counts']['todo']}, ";
            $text .= "В процесі: {$project['status_counts']['in_progress']}, ";
            $text .= "Виконано: {$project['status_counts']['done']})\n";
            $text .= "Прострочено: {$project['expired_tasks']}\n";
            $text .= "Учасників: {$project['members_count']}\n";
        }

        return $text;
    }

    private function displaySummaryTable(array $reportData): void
    {
        $this->newLine();
        $this->info('📊 ДЕТАЛЬНИЙ ЗВІТ ПО ПРОЄКТАХ:');

        $this->table(
            ['Проєкт', 'Всього задач', 'До виконання', 'В процесі', 'Виконано', 'Прострочено', 'Учасників', 'Статус'],
            collect($reportData['projects'])->map(function ($data) {
                $statusIcon = match($data['status']) {
                    'active' => '🟢',
                    'on_hold' => '🟡',
                    'completed' => '✅',
                    'cancelled' => '🔴',
                    default => '⚪',
                };

                return [
                    $data['project_name'],
                    $data['total_tasks'],
                    $data['status_counts']['todo'],
                    $data['status_counts']['in_progress'],
                    $data['status_counts']['done'],
                    $data['expired_tasks'] > 0 ? "<fg=red>{$data['expired_tasks']}</>" : "{$data['expired_tasks']}",
                    $data['members_count'],
                    $statusIcon . ' ' . $data['status'],
                ];
            })->toArray()
        );
    }

    private function displayFinalSummary(array $reportData, Report $report): void
    {
        $this->newLine();
        $this->info('📈 ЗАГАЛЬНА СТАТИСТИКА:');

        $this->table(
            ['Показник', 'Значення'],
            [
                ['Загальна кількість проєктів', $reportData['meta']['total_projects']],
                ['Всього задач', $reportData['summary']['total_tasks']],
                ['Прострочених задач', "<fg=red>{$reportData['summary']['total_expired']}</>"],
                ['Загальна кількість учасників', $reportData['summary']['total_members']],
                ['Проєктів з простроченими задачами', $reportData['statistics']['projects_with_expired_tasks']],
                ['Проєктів без задач', $reportData['statistics']['projects_without_tasks']],
                ['Середній час виконання задач', $reportData['summary']['avg_completion_days'] . ' днів'],
            ]
        );

        $this->newLine();
        $this->info('📋 РОЗПОДІЛ ЗА СТАТУСАМИ:');

        $this->table(
            ['Статус', 'Кількість', 'Відсоток'],
            [
                ['To Do', $reportData['summary']['status_distribution']['todo'],
                    round($reportData['summary']['status_distribution']['todo'] / max($reportData['summary']['total_tasks'], 1) * 100, 1) . '%'],
                ['В процесі', $reportData['summary']['status_distribution']['in_progress'],
                    round($reportData['summary']['status_distribution']['in_progress'] / max($reportData['summary']['total_tasks'], 1) * 100, 1) . '%'],
                ['Виконано', $reportData['summary']['status_distribution']['done'],
                    round($reportData['summary']['status_distribution']['done'] / max($reportData['summary']['total_tasks'], 1) * 100, 1) . '%'],
            ]
        );

        $this->newLine();
        $this->info('🔗 ДЕТАЛІ ЗВІТУ:');
        $this->line("ID звіту в базі: <fg=cyan>{$report->id}</>");
        $this->line("ID логу виконання: <fg=cyan>{$report->scheduler_log_id}</>");
        $this->line("Період: <fg=yellow>{$report->period_start->toDateString()}</> до <fg=yellow>{$report->period_end->toDateString()}</>");

        if ($report->path) {
            $this->line("Файл звіту: <fg=green>{$report->path}</>");
        }
    }

    protected function sendEmailReport($email, $reportData, $filePath = null): bool
    {
        try {
            return true;
        } catch (\Exception $e) {
            Log::error('Не вдалося надіслати звіт email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            if (!$this->option('silent')) {
                $this->warn("⚠️  Не вдалося відправити email: " . $e->getMessage());
            }

            return false;
        }
    }
}
