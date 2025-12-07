<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Services\TelegramService;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramService $telegramService)
    {
        $secretToken = config('services.telegram.webhook_secret');
        $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secretToken && $receivedToken !== $secretToken) {
            Log::warning('Невірний секретний токен вебхука', [
                'received' => $receivedToken,
                'expected' => $secretToken,
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $update = $request->all();

        Log::info('Telegram вебхук отримано', ['update' => $update]);

        $this->processUpdate($update, $telegramService);

        return response()->json(['ok' => true]);
    }

    private function processUpdate(array $update, TelegramService $telegramService): void
    {
        if (isset($update['message'])) {
            $this->processMessage($update['message'], $telegramService);
        }

        if (isset($update['callback_query'])) {
            $this->processCallbackQuery($update['callback_query'], $telegramService);
        }

        if (isset($update['inline_query'])) {
            $this->processInlineQuery($update['inline_query'], $telegramService);
        }
    }

    private function processMessage(array $message, TelegramService $telegramService): void
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        Log::info('Отримано повідомлення в Telegram', [
            'chat_id' => $chatId,
            'text' => $text,
            'from' => $message['from']['username'] ?? $message['from']['id'],
        ]);

        if (str_starts_with($text, '/')) {
            $this->processCommand($chatId, $text, $telegramService, $message);
            return;
        }

        $telegramService->sendMessage($chatId, "Отримано повідомлення: {$text}");
    }

    private function processCommand(string $chatId, string $text, TelegramService $telegramService, array $message): void
    {
        $command = strtok($text, ' ');
        $params = trim(substr($text, strlen($command)));

        switch ($command) {
            case '/start':
                $telegramService->sendMessage($chatId,
                    "👋 Вітаємо! Це бот для сповіщень про задачі.\n\n"
                    . "Доступні команди:\n"
                    . "/help - Довідка\n"
                    . "/status - Статус бота\n"
                    . "/link - Прив'язати акаунт\n"
                    . "/tasks - Мої задачі"
                );
                break;

            case '/help':
                $telegramService->sendMessage($chatId,
                    "📚 <b>Довідка по командам:</b>\n\n"
                    . "/start - Початок роботи\n"
                    . "/help - Ця довідка\n"
                    . "/status - Статус бота\n"
                    . "/link - Прив'язати акаунт до системи\n"
                    . "/tasks - Отримати список моїх задач\n"
                    . "/settings - Налаштування сповіщень\n\n"
                    . "ℹ️ Бот автоматично надсилає сповіщення про:\n"
                    . "• Створення нових задач\n"
                    . "• Коментарі до ваших задач\n"
                    . "• Зміну статусу задач"
                );
                break;

            case '/status':
                $me = $telegramService->getMe();
                if ($me['ok']) {
                    $bot = $me['result'];
                    $telegramService->sendMessage($chatId,
                        "🤖 <b>Статус бота:</b>\n\n"
                        . "Ім'я: {$bot['first_name']}\n"
                        . "Username: @{$bot['username']}\n"
                        . "ID: {$bot['id']}\n\n"
                        . "✅ Бот працює нормально"
                    );
                }
                break;

            case '/link':
                $userId = $message['from']['id'] ?? null;
                $username = $message['from']['username'] ?? null;

                if ($userId) {
                    $telegramService->sendMessage($chatId,
                        "🔗 <b>Прив'язка акаунта</b>\n\n"
                        . "Ваш Telegram ID: <code>{$userId}</code>\n"
                        . "Username: @{$username}\n\n"
                        . "Скопіюйте цей ID та введіть його в налаштуваннях вашого профілю в системі."
                    );
                }
                break;

            default:
                $telegramService->sendMessage($chatId,
                    "❌ Невідома команда. Використовуйте /help для довідки."
                );
        }
    }

    private function processCallbackQuery(array $callbackQuery, TelegramService $telegramService): void
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];

        $telegramService->callApi('answerCallbackQuery', [
            'callback_query_id' => $callbackQuery['id'],
            'text' => 'Оброблено',
        ]);

    }

    private function processInlineQuery(array $inlineQuery, TelegramService $telegramService): void
    {
    }
}
