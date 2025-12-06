<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTaskCreatedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [60, 120, 300];
    public $task;
    public $author;

    public function __construct(Task $task)
    {
        $this->task = $task->withoutRelations();
        $this->author = $task->author;
    }

    public function handle(): void
    {
        try {
            Log::channel('queue')->info('Початок обробки сповіщення про створення задачі', [
                'task_id' => $this->task->id,
                'task_title' => $this->task->title,
                'job_id' => $this->job->getJobId(),
            ]);

            $assignee = User::find($this->task->assignee_id);

            if (!$assignee) {
                Log::warning('Призначена особа не знайдена для задачі', ['task_id' => $this->task->id]);
                return;
            }

            Log::info('Сповіщення про створення задачі відправлено', [
                'task_id' => $this->task->id,
                'title' => $this->task->title,
                'assignee_id' => $assignee->id,
                'assignee_email' => $assignee->email,
                'author_id' => $this->author->id,
                'author_name' => $this->author->name,
            ]);

            $this->createNotificationRecord($assignee);

            Log::channel('queue')->info('Сповіщення успішно оброблено', [
                'task_id' => $this->task->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Помилка при відправці сповіщення: ' . $e->getMessage(), [
                'task_id' => $this->task->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    protected function sendEmailNotification(User $assignee): void
    {
        try {
            Mail::to($assignee->email)->send(
                new \App\Mail\TaskCreated($this->task, $this->author)
            );

            Log::info('Email сповіщення відправлено', [
                'to' => $assignee->email,
                'task_id' => $this->task->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Помилка при відправці email: ' . $e->getMessage());
        }
    }

    protected function createNotificationRecord(User $assignee): void
    {
        try {
            if (\Schema::hasTable('notifications')) {
                $assignee->notifications()->create([
                    'type' => 'task_created',
                    'data' => [
                        'task_id' => $this->task->id,
                        'task_title' => $this->task->title,
                        'author_id' => $this->author->id,
                        'author_name' => $this->author->name,
                        'project_id' => $this->task->project_id,
                        'priority' => $this->task->priority,
                        'due_date' => $this->task->due_date->toDateString(),
                        'message' => 'Вам призначено нову задачу',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Не вдалося створити запис сповіщення: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Завдання SendTaskCreatedNotification не вдалося: ' . $exception->getMessage(), [
            'task_id' => $this->task->id,
            'exception' => $exception,
        ]);

    }
}
