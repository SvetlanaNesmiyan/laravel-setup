<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(string $chatId, string $text, array $options = []): array
    {
        $token = config('services.telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);

        Log::info('TelegramService: Sending message', [
            'url' => $url,
            'params' => $params
        ]);

        try {
            $response = Http::timeout(30)->post($url, $params);
            $result = $response->json();

            Log::info('TelegramService: Response', $result);

            return $result;
        } catch (\Exception $e) {
            Log::error('TelegramService Error: ' . $e->getMessage());
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'description' => 'Виникла помилка при виконанні запиту',
            ];
        }
    }

    public function sendTaskCreatedNotification(string $chatId, array $taskData): array
    {
        $text = "🆕 *Нова задача*\n\n"
            . "📝 *Назва:* {$taskData['title']}\n"
            . "📋 *Опис:* {$taskData['description']}\n"
            . "👤 *Автор:* {$taskData['author_name']}\n"
            . "🎯 *Призначено:* {$taskData['assignee_name']}\n"
            . "📅 *Термін:* {$taskData['due_date']}\n"
            . "⚡ *Пріоритет:* " . $this->translatePriority($taskData['priority'] ?? 'medium') . "\n"
            . "📂 *Проєкт:* {$taskData['project_name']}\n\n"
            . "ID: `{$taskData['task_id']}`";

        return $this->sendMessage($chatId, $text);
    }

    private function translatePriority(string $priority): string
    {
        return match($priority) {
            'high' => 'Високий',
            'medium' => 'Середній',
            'low' => 'Низький',
            default => 'Середній',
        };
    }
}
