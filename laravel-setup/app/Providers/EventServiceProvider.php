<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\TaskCreated;
use App\Events\CommentCreated;
use App\Listeners\SendTaskCreatedNotificationListener;
use App\Listeners\SendTelegramTaskCreatedNotification;
use App\Listeners\SendTelegramCommentCreatedNotification;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        TaskCreated::class => [
            SendTaskCreatedNotificationListener::class,
            SendTelegramTaskCreatedNotification::class, // Додаємо Telegram сповіщення
        ],
        CommentCreated::class => [
            SendTelegramCommentCreatedNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
