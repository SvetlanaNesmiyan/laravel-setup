<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use App\Http\Requests\StoreCommentRequest;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function taskComments(Task $task)
    {
        if (!$task->project->users->contains(auth()->id())) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $comments = $task->comments()->with('author')->get();

        return response()->json([
            'data' => $comments
        ]);
    }

    public function storeInTask(StoreCommentRequest $request, Task $task)
    {
        if (!$task->project->users->contains(auth()->id())) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $comment = Comment::create([
            'task_id' => $task->id,
            'author_id' => auth()->id(),
            'body' => $request->body,
        ]);

        return response()->json([
            'message' => 'Comment created successfully',
            'data' => $comment->load('author')
        ], Response::HTTP_CREATED);
    }

    public function destroy(Comment $comment)
    {
        if ($comment->author_id !== auth()->id()) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ]);
    }
}
