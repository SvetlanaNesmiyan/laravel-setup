<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckProjectAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $projectId = $request->route('project') ?? $request->route('id');

        if (!$projectId) {
            return response()->json(['message' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->user();

        if (!$project->users->contains($user->id)) {
            return response()->json(['message' => 'Access denied to this project'], Response::HTTP_FORBIDDEN);
        }

        if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE']) && $project->owner_id !== $user->id) {
            return response()->json(['message' => 'Only project owner can perform this action'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
