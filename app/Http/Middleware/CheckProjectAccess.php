<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Project;

class CheckProjectAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $projectId = $request->route('project')?->id ?? $request->route('id') ?? $request->route('project');

        if (!$projectId && $request->route('task')) {
            $projectId = $request->route('task')->project_id;
        }

        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Проєкт не знайдено'], 404);
        }

        $user = $request->user();

        if ($project->owner_id !== $user->id && !$project->users->contains($user->id)) {
            return response()->json(['message' => 'Заборонено. Ви не є учасником цього проєкту.'], 403);
        }

        $request->merge(['project' => $project]);

        return $next($request);
    }
}
