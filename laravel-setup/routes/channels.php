<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('project.{projectId}', function (User $user, $projectId) {
    $project = Project::find($projectId);

    if (!$project) {
        return false;
    }

    $isOwner = $project->owner_id === $user->id;
    $isMember = $project->users()->where('user_id', $user->id)->exists();

    if ($isOwner || $isMember) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $isOwner ? 'owner' : 'member',
        ];
    }

    return false;
});
