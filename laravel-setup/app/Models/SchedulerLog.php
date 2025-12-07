<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchedulerLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'command',
        'description',
        'status',
        'parameters',
        'output',
        'execution_time',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'output' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function start(string $command, ?string $description = null, ?array $parameters = null): self
    {
        return self::create([
            'command' => $command,
            'description' => $description,
            'status' => 'running',
            'parameters' => $parameters,
            'started_at' => now(),
        ]);
    }

    public function complete(?array $output = null, ?int $executionTime = null): void
    {
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'execution_time' => $executionTime ?? $this->started_at->diffInMilliseconds(now()),
            'finished_at' => now(),
        ]);
    }

    public function fail(string $errorMessage, ?array $output = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'output' => $output,
            'execution_time' => $this->started_at->diffInMilliseconds(now()),
            'finished_at' => now(),
        ]);
    }

    public static function isRunning(string $command): bool
    {
        return self::where('command', $command)
            ->where('status', 'running')
            ->where('started_at', '>', now()->subHours(1))
            ->exists();
    }

    public static function getStats(string $command, int $days = 7): array
    {
        $logs = self::where('command', $command)
            ->where('started_at', '>', now()->subDays($days))
            ->get();

        return [
            'total' => $logs->count(),
            'completed' => $logs->where('status', 'completed')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'avg_execution_time' => $logs->where('status', 'completed')->avg('execution_time'),
            'last_run' => $logs->max('started_at'),
            'success_rate' => $logs->count() > 0
                ? round(($logs->where('status', 'completed')->count() / $logs->count()) * 100, 2)
                : 0,
        ];
    }

    public static function cleanup(int $days = 30): int
    {
        return self::where('created_at', '<', now()->subDays($days))->delete();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
