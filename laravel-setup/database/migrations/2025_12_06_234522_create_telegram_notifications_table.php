<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('method');
            $table->string('notification_type')->nullable();
            $table->string('chat_id')->nullable();
            $table->json('params');
            $table->json('response');
            $table->boolean('success')->default(false);
            $table->bigInteger('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('notification_type');
            $table->index('chat_id');
            $table->index('success');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_notifications');
    }
};
