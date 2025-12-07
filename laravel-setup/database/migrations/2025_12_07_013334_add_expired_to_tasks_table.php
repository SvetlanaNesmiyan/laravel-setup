<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('tasks', function (Blueprint $table) {
                $table->timestamp('expired_at')->nullable()->after('due_date');
                $table->index('expired_at');
                $table->index(['status', 'created_at']);
            });

            DB::statement("ALTER TABLE tasks ADD CONSTRAINT check_status_values CHECK (status IN ('todo', 'in_progress', 'done', 'expired'))");

        } else {
            DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('todo', 'in_progress', 'done', 'expired') DEFAULT 'todo'");

            Schema::table('tasks', function (Blueprint $table) {
                $table->timestamp('expired_at')->nullable()->after('due_date');
                $table->index('expired_at');
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['expired_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn('expired_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tasks DROP CONSTRAINT IF EXISTS check_status_values");
        } else {
            DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('todo', 'in_progress', 'done') DEFAULT 'todo'");
        }
    }
};
