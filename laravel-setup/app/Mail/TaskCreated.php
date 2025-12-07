<?php

namespace App\Mail;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $author;

    public function __construct(Task $task, $author)
    {
        $this->task = $task;
        $this->author = $author;
    }

    public function build(): self
    {
        return $this->subject('Нова задача: ' . $this->task->title)
            ->view('emails.task-created')
            ->with([
                'task' => $this->task,
                'author' => $this->author,
                'dueDate' => $this->task->due_date->format('d.m.Y'),
            ]);
    }
}
