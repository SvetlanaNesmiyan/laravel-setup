<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('check.project.access')->only(['show', 'update', 'destroy']);
    }

    public function index()
    {
        $user = auth()->user();

        $projects = $user->projects()->with(['owner', 'users'])->get();

        return response()->json([
            'data' => $projects
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $project = Project::create([
            'name' => $request->name,
            'owner_id' => auth()->id(),
        ]);

        $project->users()->attach(auth()->id(), ['role' => 'owner']);

        return response()->json([
            'message' => 'Project created successfully',
            'data' => $project->load(['owner', 'users'])
        ], Response::HTTP_CREATED);
    }

    public function show(Project $project)
    {
        return response()->json([
            'data' => $project->load(['owner', 'users', 'tasks'])
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return response()->json([
            'message' => 'Project updated successfully',
            'data' => $project->load(['owner', 'users'])
        ]);
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully'
        ]);
    }
}
