<?php
// lab1_laravel
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'author_id', 'assignee_id', 'title',
        'description', 'status', 'priority', 'due_date'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    protected static function booted()
    {
        static::created(function ($task) {
            event(new \App\Events\TaskCreated($task));
        });

        static::updated(function ($task) {
            $oldStatus = $task->getOriginal('status');
            if ($oldStatus !== $task->status) {
                event(new \App\Events\TaskUpdated($task, $oldStatus));
            }
        });

        static::deleted(function ($task) {
            event(new \App\Events\TaskDeleted($task));
        });
    }
}
