<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->json('parameters')->nullable();
            $table->json('output')->nullable();
            $table->integer('execution_time')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('command');
            $table->index('status');
            $table->index('started_at');
            $table->index(['command', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_logs');
    }
};
