<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Jobs\SendTaskCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskCreatedNotificationListener implements ShouldQueue
{
    public $queue = 'notifications';
    public $delay = 5;
    public $tries = 3;

    public function handle(TaskCreated $event): void
    {
        SendTaskCreatedNotification::dispatch($event->task)
            ->onQueue('notifications')
            ->delay(now()->addSeconds($this->delay));
    }

    public function failed(TaskCreated $event, \Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error(
            'Не вдалося обробити подію TaskCreated: ' . $exception->getMessage(),
            [
                'task_id' => $event->task->id,
                'event' => 'TaskCreated',
                'exception' => $exception,
            ]
        );
    }
}
