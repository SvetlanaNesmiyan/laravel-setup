<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Jobs\SendTelegramMessageJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTelegramTaskCreatedNotification implements ShouldQueue
{
    public $queue = 'telegram';
    public $delay = 2; // Затримка 2 секунди

    public function handle(TaskCreated $event): void
    {
        $task = $event->task;

        $notificationData = [
            'task_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'author_name' => $task->author->name ?? 'Невідомо',
            'assignee_name' => $task->assignee->name ?? 'Невідомо',
            'due_date' => $task->due_date->format('d.m.Y'),
            'priority' => $task->priority,
            'project_name' => $task->project->name ?? 'Невідомо',
        ];

        if ($task->assignee && $task->assignee->telegram_chat_id) {
            SendTelegramMessageJob::dispatch(
                'sendTaskCreatedNotification',
                $notificationData,
                'task_created',
                $task->assignee->telegram_chat_id
            )->delay(now()->addSeconds($this->delay));
        }

        SendTelegramMessageJob::dispatch(
            'sendTaskCreatedNotification',
            $notificationData,
            'task_created'
        )->delay(now()->addSeconds($this->delay));
    }

    public function failed(TaskCreated $event, \Throwable $exception): void
    {
        \Log::error('Не вдалося обробити подію TaskCreated для Telegram', [
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
