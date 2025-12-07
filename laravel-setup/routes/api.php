<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Task;
use App\Models\Comment;
use App\Models\User;
use App\Models\Project;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/test/task', function (Request $request) {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $task = Task::create([
            'project_id' => $request->project_id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'todo',
            'author_id' => auth()->id(),
            'assignee_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Task created successfully',
            'task_id' => $task->id,
            'task' => $task
        ]);
    });

    Route::put('/test/task/{task}/status', function (Request $request, Task $task) {
        $request->validate([
            'status' => 'required|in:todo,in_progress,done,expired',
        ]);

        $oldStatus = $task->status;
        $task->status = $request->status;
        $task->save();

        return response()->json([
            'message' => 'Task status updated',
            'old_status' => $oldStatus,
            'new_status' => $task->status,
        ]);
    });

    Route::post('/test/comment', function (Request $request) {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'content' => 'required|string',
        ]);

        $comment = Comment::create([
            'task_id' => $request->task_id,
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);

        return response()->json([
            'message' => 'Comment created successfully',
            'comment_id' => $comment->id,
        ]);
    });
});
