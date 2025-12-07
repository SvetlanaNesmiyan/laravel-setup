<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $oldStatus;

    public function __construct(Task $task, $oldStatus = null)
    {
        $this->task = $task->load('project', 'assignee');
        $this->oldStatus = $oldStatus;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('project.' . $this->task->project_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->task->status,
            'project_id' => $this->task->project_id,
            'updated_at' => $this->task->updated_at,
        ];
    }
}
