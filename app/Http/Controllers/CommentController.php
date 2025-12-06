<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Заборонено. Тільки автор коментаря може видаляти.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Коментар успішно видалено']);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Заборонено. Тільки автор коментаря може оновлювати.'], 403);
        }

        $request->validate([
            'body' => 'required|string|min:1|max:1000',
        ]);

        $comment->update(['body' => $request->body]);

        return response()->json([
            'message' => 'Коментар успішно оновлено',
            'comment' => $comment->fresh()->load('author'),
        ]);
    }
}
