<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Jobs\SendTelegramMessageJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTelegramCommentCreatedNotification implements ShouldQueue
{
    public $queue = 'telegram';
    public $delay = 2;

    public function handle(CommentCreated $event): void
    {
        $comment = $event->comment;
        $task = $comment->task;

        $notificationData = [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'author_name' => $comment->author->name ?? 'Невідомо',
            'body' => $comment->body,
            'project_name' => $task->project->name ?? 'Невідомо',
        ];

        $recipients = [];

        if ($task->author && $task->author->telegram_chat_id) {
            $recipients[] = $task->author->telegram_chat_id;
        }

        if ($task->assignee && $task->assignee->telegram_chat_id) {
            $recipients[] = $task->assignee->telegram_chat_id;
        }

        $recipients = array_unique($recipients);

        foreach ($recipients as $chatId) {
            SendTelegramMessageJob::dispatch(
                'sendCommentCreatedNotification',
                $notificationData,
                'comment_created',
                $chatId
            )->delay(now()->addSeconds($this->delay));
        }

        SendTelegramMessageJob::dispatch(
            'sendCommentCreatedNotification',
            $notificationData,
            'comment_created'
        )->delay(now()->addSeconds($this->delay));
    }

    public function failed(CommentCreated $event, \Throwable $exception): void
    {
        \Log::error('Не вдалося обробити подію CommentCreated для Telegram', [
            'comment_id' => $event->comment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
