<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [60, 300, 900];

    private string $method;
    private array $params;
    private ?string $notificationType;
    private ?string $chatId;

    public function __construct(
        string $method,
        array $params = [],
        ?string $notificationType = null,
        ?string $chatId = null
    ) {
        $this->method = $method;
        $this->params = $params;
        $this->notificationType = $notificationType;
        $this->chatId = $chatId;

        $this->onQueue('telegram');
    }

    public function handle(TelegramService $telegramService): void
    {
        $startTime = microtime(true);

        Log::info('Початок відправки Telegram повідомлення', [
            'job_id' => $this->job->getJobId(),
            'method' => $this->method,
            'notification_type' => $this->notificationType,
            'chat_id' => $this->chatId,
        ]);

        try {
            $result = match($this->method) {
                'sendMessage' => $telegramService->sendMessage(
                    $this->chatId ?? config('services.telegram.chat_id'),
                    $this->params['text'] ?? '',
                    $this->params
                ),
                'sendTaskCreatedNotification' => $telegramService->sendTaskCreatedNotification(
                    $this->chatId ?? config('services.telegram.chat_id'),
                    $this->params
                ),
                'sendCommentCreatedNotification' => $telegramService->sendCommentCreatedNotification(
                    $this->chatId ?? config('services.telegram.chat_id'),
                    $this->params
                ),
                'sendTaskStatusChangedNotification' => $telegramService->sendTaskStatusChangedNotification(
                    $this->chatId ?? config('services.telegram.chat_id'),
                    $this->params
                ),
                'broadcastMessage' => $telegramService->broadcastMessage(
                    $this->params['text'] ?? '',
                    $this->params
                ),
                default => ['ok' => false, 'error' => 'Невідомий метод'],
            };

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Telegram повідомлення відправлено', [
                'job_id' => $this->job->getJobId(),
                'method' => $this->method,
                'result' => $result['ok'] ?? false,
                'message_id' => $result['result']['message_id'] ?? null,
                'chat_id' => $result['result']['chat']['id'] ?? null,
                'execution_time_ms' => $executionTime,
                'notification_type' => $this->notificationType,
            ]);

            $this->saveNotificationLog($result);

        } catch (\Exception $e) {
            Log::error('Помилка при відправці Telegram повідомлення', [
                'job_id' => $this->job->getJobId(),
                'method' => $this->method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Завдання SendTelegramMessageJob не вдалося', [
            'job_id' => $this->job->getJobId(),
            'method' => $this->method,
            'error' => $exception->getMessage(),
            'notification_type' => $this->notificationType,
        ]);
    }

    private function saveNotificationLog(array $result): void
    {
        try {
            if (\Schema::hasTable('telegram_notifications')) {
                \DB::table('telegram_notifications')->insert([
                    'method' => $this->method,
                    'notification_type' => $this->notificationType,
                    'chat_id' => $this->chatId,
                    'params' => json_encode($this->params),
                    'response' => json_encode($result),
                    'success' => $result['ok'] ?? false,
                    'message_id' => $result['result']['message_id'] ?? null,
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Не вдалося зберегти лог Telegram повідомлення', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
