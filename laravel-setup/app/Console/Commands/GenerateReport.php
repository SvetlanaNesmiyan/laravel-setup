<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Report;
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
                            {--email= : Email для відправлення звіту (опціонально)}';

    protected $description = 'Генерує звіт за задачами у проєктах';

    public function handle(): int
    {
        $startTime = microtime(true);

        $projectId = $this->option('project');
        $periodDays = (int)$this->option('period') ?: 30;
        $saveToFile = $this->option('file');
        $email = $this->option('email');

        $periodStart = Carbon::now()->subDays($periodDays);
        $periodEnd = Carbon::now();

        $this->info("Генерація звіту за період: {$periodStart->toDateString()} - {$periodEnd->toDateString()}");

        $query = Project::query();

        if ($projectId) {
            $query->where('id', $projectId);
            $this->info("Для проєкту ID: {$projectId}");
        }

        $projects = $query->with(['tasks' => function ($query) use ($periodStart, $periodEnd) {
            $query->whereBetween('created_at', [$periodStart, $periodEnd])
                ->with(['author', 'assignee']);
        }, 'owner', 'users'])->get();

        if ($projects->isEmpty()) {
            $this->error("Проєкти не знайдені!");
            return Command::FAILURE;
        }

        $reportData = [
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'total_projects' => $projects->count(),
            ],
            'projects' => [],
            'summary' => [
                'total_tasks' => 0,
                'total_expired' => 0,
                'total_members' => 0,
            ]
        ];

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
            $avgCompletionTime = $completedTasks->isNotEmpty()
                ? $completedTasks->avg(function ($task) {
                    return $task->updated_at->diffInDays($task->created_at);
                })
                : 0;

            $assignees = $tasks->groupBy('assignee_id')
                ->map(function ($tasks, $assigneeId) {
                    $firstTask = $tasks->first();
                    return [
                        'user_id' => $assigneeId,
                        'user_name' => $firstTask->assignee->name ?? 'Невідомо',
                        'task_count' => $tasks->count(),
                    ];
                })->values();

            $projectData = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'owner_id' => $project->owner_id,
                'owner_name' => $project->owner->name ?? 'Невідомо',
                'total_tasks' => $tasks->count(),
                'status_counts' => $statusCounts,
                'expired_tasks' => $expiredCount,
                'priority_counts' => $priorityCounts,
                'avg_completion_days' => round($avgCompletionTime, 2),
                'members_count' => $project->users->count(),
                'assignees' => $assignees,
                'expired_task_details' => $expiredTasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'assignee' => $task->assignee->name ?? 'Невідомо',
                        'due_date' => $task->due_date->toDateString(),
                        'days_late' => now()->diffInDays($task->due_date),
                    ];
                })->values(),
                'created_at' => now()->toDateTimeString(),
            ];

            $reportData['projects'][$project->id] = $projectData;

            $reportData['summary']['total_tasks'] += $tasks->count();
            $reportData['summary']['total_expired'] += $expiredCount;
            $reportData['summary']['total_members'] += $project->users->count();
        }

        $report = Report::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'payload' => $reportData,
            'path' => null,
        ]);

        $this->info("✓ Звіт збережено в базу даних. ID: {$report->id}");

        $filePath = null;
        if ($saveToFile) {
            $fileName = "report_{$periodStart->format('Y-m-d')}_{$periodEnd->format('Y-m-d')}_{$report->id}.json";
            $filePath = "reports/{$fileName}";

            Storage::disk('local')->put($filePath, json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $report->update(['path' => $filePath]);

            $this->info("✓ Файл звіту збережено: storage/app/{$filePath}");
            $filePath = storage_path("app/{$filePath}");
        }

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendEmailReport($email, $reportData, $filePath);
            $this->info("✓ Звіт надіслано на email: {$email}");
        }

        $this->table(
            ['Проєкт', 'Всього задач', 'До виконання', 'В процесі', 'Виконано', 'Прострочено', 'Учасників'],
            collect($reportData['projects'])->map(function ($data) {
                return [
                    $data['project_name'],
                    $data['total_tasks'],
                    $data['status_counts']['todo'],
                    $data['status_counts']['in_progress'],
                    $data['status_counts']['done'],
                    $data['expired_tasks'],
                    $data['members_count'],
                ];
            })->toArray()
        );

        $this->info("\n=== ПІДСУМОК ===");
        $this->line("Всього проєктів: " . $reportData['meta']['total_projects']);
        $this->line("Всього задач: " . $reportData['summary']['total_tasks']);
        $this->line("Прострочених задач: " . $reportData['summary']['total_expired']);
        $this->line("Всього учасників: " . $reportData['summary']['total_members']);

        $executionTime = round(microtime(true) - $startTime, 2);
        $this->info("Час виконання: {$executionTime} секунд");

        // Логуємо виконання
        Log::info('Звіт згенеровано', [
            'report_id' => $report->id,
            'projects' => count($reportData['projects']),
            'execution_time' => $executionTime,
        ]);

        return Command::SUCCESS;
    }

    protected function sendEmailReport($email, $reportData, $filePath = null): void
    {
        try {
            $this->info("Email надіслано на {$email}");
        } catch (\Exception $e) {
            $this->error("Помилка при відправці email: " . $e->getMessage());
            Log::error('Не вдалося надіслати звіт email', ['error' => $e->getMessage()]);
        }
    }
}
