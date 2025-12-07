<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramTestCommand extends Command
{
    protected $signature = 'telegram:test
                            {--message= : Текст повідомлення для відправки}
                            {--chat= : ID чату (за замовчуванням з конфігурації)}
                            {--status : Перевірити статус бота}
                            {--broadcast : Надіслати broadcast повідомлення}
                            {--task : Надіслати тестове повідомлення про задачу}';

    protected $description = 'Тестування інтеграції з Telegram API';

    public function handle(TelegramService $telegramService): int
    {
        if ($this->option('status')) {
            return $this->testStatus($telegramService);
        }

        if ($this->option('broadcast')) {
            return $this->testBroadcast($telegramService);
        }

        if ($this->option('task')) {
            return $this->testTaskNotification($telegramService);
        }

        return $this->testMessage($telegramService);
    }

    protected function testStatus(TelegramService $telegramService): int
    {
        $this->info('🔄 Перевірка статусу Telegram бота...');

        if (!$telegramService->isAvailable()) {
            $this->error('❌ Telegram бот недоступний');
            $this->line('Перевірте налаштування у .env файлі:');
            $this->line('• TELEGRAM_BOT_TOKEN');
            $this->line('• TELEGRAM_CHAT_ID');
            return Command::FAILURE;
        }

        $me = $telegramService->getMe();

        if ($me['ok']) {
            $bot = $me['result'];
            $this->info('✅ Telegram бот доступний');
            $this->line('🤖 Ім\'я: ' . $bot['first_name']);
            $this->line('👤 Username: @' . ($bot['username'] ?? 'не вказано'));
            $this->line('🆔 ID: ' . $bot['id']);

            $chats = $telegramService->getChats();
            $this->line('💬 Налаштовані чати: ' . ($chats ? implode(', ', $chats) : 'не налаштовано'));

            return Command::SUCCESS;
        }

        $this->error('❌ Помилка при отриманні інформації про бота');
        $this->line('Помилка: ' . ($me['description'] ?? 'Невідома помилка'));

        return Command::FAILURE;
    }

    protected function testMessage(TelegramService $telegramService): int
    {
        $message = $this->option('message') ?? 'Тестове повідомлення з Laravel додатку';
        $chatId = $this->option('chat') ?? config('services.telegram.chat_id');

        if (!$chatId) {
            $this->error('❌ ID чату не вказано');
            $this->line('Вкажіть --chat=CHAT_ID або налаштуйте TELEGRAM_CHAT_ID в .env');
            return Command::FAILURE;
        }

        $this->info("📤 Відправка тестового повідомлення до чату {$chatId}...");

        $result = $telegramService->sendMessage($chatId, $message, [
            'parse_mode' => 'HTML',
        ]);

        if ($result['ok'] ?? false) {
            $this->info('✅ Повідомлення успішно відправлено');
            $this->line('📝 ID повідомлення: ' . ($result['result']['message_id'] ?? 'невідомо'));
            $this->line('💬 Чат: ' . ($result['result']['chat']['title'] ?? $result['result']['chat']['id']));
            $this->line('📅 Дата: ' . date('Y-m-d H:i:s', $result['result']['date']));

            return Command::SUCCESS;
        }

        $this->error('❌ Не вдалося відправити повідомлення');
        $this->line('Помилка: ' . ($result['description'] ?? 'Невідома помилка'));

        return Command::FAILURE;
    }

    protected function testBroadcast(TelegramService $telegramService): int
    {
        $message = $this->option('message') ?? 'Broadcast тестове повідомлення';

        $this->info('📢 Відправка broadcast повідомлення до всіх чатів...');

        $results = $telegramService->broadcastMessage($message, [
            'parse_mode' => 'HTML',
        ]);

        $success = 0;
        $failed = 0;

        foreach ($results as $chatId => $result) {
            if ($result['ok'] ?? false) {
                $success++;
                $this->line("✅ Чат {$chatId}: відправлено");
            } else {
                $failed++;
                $this->line("❌ Чат {$chatId}: помилка");
            }
        }

        $this->info("\n📊 Підсумок:");
        $this->line("✅ Успішно: {$success}");
        $this->line("❌ Невдало: {$failed}");
        $this->line("📋 Всього: " . count($results));

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    protected function testTaskNotification(TelegramService $telegramService): int
    {
        $chatId = $this->option('chat') ?? config('services.telegram.chat_id');

        if (!$chatId) {
            $this->error('❌ ID чату не вказано');
            return Command::FAILURE;
        }

        $this->info("📋 Відправка тестового повідомлення про задачу до чату {$chatId}...");

        $taskData = [
            'task_id' => 999,
            'title' => 'Тестова задача',
            'description' => 'Це тестовий опис задачі для перевірки Telegram інтеграції.',
            'author_name' => 'Адміністратор Системи',
            'assignee_name' => 'Тестовий Користувач',
            'due_date' => date('d.m.Y', strtotime('+7 days')),
            'priority' => 'high',
            'project_name' => 'Тестовий Проєкт',
        ];

        $result = $telegramService->sendTaskCreatedNotification($chatId, $taskData);

        if ($result['ok'] ?? false) {
            $this->info('✅ Повідомлення про задачу успішно відправлено');
            return Command::SUCCESS;
        }

        $this->error('❌ Не вдалося відправити повідомлення про задачу');
        $this->line('Помилка: ' . ($result['description'] ?? 'Невідома помилка'));

        return Command::FAILURE;
    }
}
