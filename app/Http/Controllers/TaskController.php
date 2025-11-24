<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $query = Task::with(['project', 'author', 'assignee', 'comments'])
            ->whereHas('project.users', function ($q) {
                $q->where('users.id', auth()->id());
            });

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('assignee_id')) {
            $query->where('assignee_id', $request->assignee_id);
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $tasks = $query->get();

        return response()->json([
            'data' => $tasks
        ]);
    }

    public function projectTasks(Project $project)
    {
        if (!$project->users->contains(auth()->id())) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $tasks = $project->tasks()->with(['author', 'assignee', 'comments'])->get();

        return response()->json([
            'data' => $tasks
        ]);
    }

    public function storeInProject(StoreTaskRequest $request, Project $project)
    {
        if (!$project->users->contains(auth()->id())) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $task = Task::create([
            'project_id' => $project->id,
            'author_id' => auth()->id(),
            'assignee_id' => $request->assignee_id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => $request->status ?? 'todo',
            'priority' => $request->priority ?? 'medium',
            'due_date' => $request->due_date,
        ]);

        return response()->json([
            'message' => 'Task created successfully',
            'data' => $task->load(['project', 'author', 'assignee'])
        ], Response::HTTP_CREATED);
    }

    public function show(Task $task)
    {
        if (!$task->project->users->contains(auth()->id())) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'data' => $task->load(['project', 'author', 'assignee', 'comments.author'])
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $user = auth()->user();
        if ($task->author_id !== $user->id && $task->project->owner_id !== $user->id) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $task->update($request->validated());

        return response()->json([
            'message' => 'Task updated successfully',
            'data' => $task->load(['project', 'author', 'assignee'])
        ]);
    }

    public function destroy(Task $task)
    {
        $user = auth()->user();
        if ($task->author_id !== $user->id && $task->project->owner_id !== $user->id) {
            return response()->json([
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully'
        ]);
    }
}
