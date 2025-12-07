<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('check.project.access')->only(['show', 'update', 'destroy', 'tasks', 'storeTask', 'addMember', 'removeMember']);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = $user->projects()
            ->with(['owner', 'users'])
            ->withCount('tasks')
            ->get();

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|min:3',
        ]);

        $project = Project::create([
            'owner_id' => $request->user()->id,
            'name' => $request->name,
        ]);

        $project->users()->attach($request->user()->id, ['role' => 'owner']);

        $project->load('owner', 'users');

        return response()->json([
            'message' => 'Проєкт успішно створено',
            'project' => $project,
        ], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->load(['owner', 'users', 'tasks.assignee', 'tasks.author']);
        return response()->json($project);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        if ($project->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Заборонено. Тільки власник проєкту може оновлювати.'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255|min:3',
        ]);

        $project->update($request->only('name'));

        return response()->json([
            'message' => 'Проєкт успішно оновлено',
            'project' => $project->fresh(),
        ]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        if ($project->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Заборонено. Тільки власник проєкту може видаляти.'], 403);
        }

        $project->delete();

        return response()->json(null, 204);
    }

    public function tasks(Request $request, Project $project): JsonResponse
    {
        $tasks = $project->tasks()
            ->with(['author', 'assignee', 'comments' => function($query) {
                $query->latest()->limit(5);
            }])
            ->latest()
            ->get();

        return response()->json($tasks);
    }

    public function storeTask(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'assignee_id' => 'required|exists:users,id',
            'due_date' => 'required|date|after:today',
            'priority' => 'required|in:low,medium,high',
        ]);

        if (!$project->users->contains($request->assignee_id)) {
            return response()->json([
                'message' => 'Призначена особа має бути учасником проєкту'
            ], 422);
        }

        $task = Task::create([
            'project_id' => $project->id,
            'author_id' => $request->user()->id,
            'assignee_id' => $request->assignee_id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'todo',
            'priority' => $request->priority,
            'due_date' => $request->due_date,
        ]);

        event(new \App\Events\TaskCreated($task));

        $task->load('author', 'assignee');

        return response()->json([
            'message' => 'Задачу успішно створено',
            'task' => $task,
        ], 201);
    }

    public function addMember(Request $request, Project $project): JsonResponse
    {
        if ($project->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Заборонено. Тільки власник проєкту може додавати учасників.'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:member,admin',
        ]);

        if ($project->users()->where('users.id', $request->user_id)->exists()) {
            return response()->json(['message' => 'Користувач вже є учасником проєкту'], 422);
        }

        $project->users()->attach($request->user_id, ['role' => $request->role]);

        return response()->json([
            'message' => 'Учасника успішно додано',
            'project' => $project->fresh()->load('users'),
        ]);
    }

    public function removeMember(Request $request, Project $project, $userId): JsonResponse
    {
        if ($project->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Заборонено. Тільки власник проєкту може видаляти учасників.'], 403);
        }

        if ($project->owner_id == $userId) {
            return response()->json(['message' => 'Не можна видалити власника проєкту'], 422);
        }

        $project->users()->detach($userId);

        return response()->json([
            'message' => 'Учасника успішно видалено',
            'project' => $project->fresh()->load('users'),
        ]);
    }
}
