<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
                            {action : Дія (set, delete, info)}
                            {--url= : URL вебхука для встановлення}
                            {--secret= : Секретний токен для вебхука}';

    protected $description = 'Керування вебхуками Telegram';

    public function handle(TelegramService $telegramService): int
    {
        $action = $this->argument('action');

        return match($action) {
            'set' => $this->setWebhook($telegramService),
            'delete' => $this->deleteWebhook($telegramService),
            'info' => $this->webhookInfo($telegramService),
            default => $this->showHelp(),
        };
    }

    protected function setWebhook(TelegramService $telegramService): int
    {
        $url = $this->option('url') ?? route('webhook.telegram');

        if (!$url) {
            $this->error('❌ URL вебхука не вказано');
            return Command::FAILURE;
        }

        $this->info("🔄 Встановлення вебхука на URL: {$url}");

        $secretToken = $this->option('secret') ?? Str::random(32);
        $this->line("🔑 Секретний токен: {$secretToken}");

        $result = $telegramService->setWebhook($url, [
            'secret_token' => $secretToken,
            'drop_pending_updates' => true,
        ]);

        if ($result['ok'] ?? false) {
            $this->info('✅ Вебхук успішно встановлено');
            $this->line('📝 Опис: ' . ($result['description'] ?? 'Немає опису'));

            config(['services.telegram.webhook_secret' => $secretToken]);

            return Command::SUCCESS;
        }

        $this->error('❌ Не вдалося встановити вебхук');
        $this->line('Помилка: ' . ($result['description'] ?? 'Невідома помилка'));

        return Command::FAILURE;
    }

    protected function deleteWebhook(TelegramService $telegramService): int
    {
        $this->info('🔄 Видалення вебхука...');

        $result = $telegramService->deleteWebhook();

        if ($result['ok'] ?? false) {
            $this->info('✅ Вебхук успішно видалено');
            $this->line('📝 Опис: ' . ($result['description'] ?? 'Немає опису'));

            config(['services.telegram.webhook_secret' => null]);

            return Command::SUCCESS;
        }

        $this->error('❌ Не вдалося видалити вебхук');
        $this->line('Помилка: ' . ($result['description'] ?? 'Невідома помилка'));

        return Command::FAILURE;
    }

    protected function webhookInfo(TelegramService $telegramService): int
    {
        $this->info('🔄 Отримання інформації про вебхук...');

        $result = $telegramService->getWebhookInfo();

        if ($result['ok'] ?? false) {
            $info = $result['result'];

            $this->info('📊 Інформація про вебхук:');
            $this->line('🌐 URL: ' . ($info['url'] ?? 'не встановлено'));
            $this->line('✅ Працює: ' . ($info['has_custom_certificate'] ? 'з власним сертифікатом' : 'з сертифікатом Telegram'));
            $this->line('⏳ Очікуючих оновлень: ' . ($info['pending_update_count'] ?? 0));

            if (!empty($info['last_error_date'])) {
                $this->line('⚠️ Остання помилка: ' . date('Y-m-d H:i:s', $info['last_error_date']));
                $this->line('📝 Опис помилки: ' . ($info['last_error_message'] ?? 'невідомо'));
            }

            if (!empty($info['max_connections'])) {
                $this->line('🔌 Макс. з\'єднань: ' . $info['max_connections']);
            }

            if (!empty($info['allowed_updates'])) {
                $this->line('📝 Дозволені оновлення: ' . implode(', ', $info['allowed_updates']));
            }

            return Command::SUCCESS;
        }

        $this->error('❌ Не вдалося отримати інформацію про вебхук');

        return Command::FAILURE;
    }

    protected function showHelp(): int
    {
        $this->info('📚 Доступні команди:');
        $this->line('• telegram:webhook set [--url=] [--secret=]');
        $this->line('• telegram:webhook delete');
        $this->line('• telegram:webhook info');

        return Command::FAILURE;
    }
}
