<?php
// lab1_laravel

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('TRUNCATE TABLE comments, tasks, project_user, projects, users RESTART IDENTITY CASCADE');

        $users = User::factory(8)->create();

        $projects = Project::factory(5)->create([
            'owner_id' => function() use ($users) {
                return $users->random()->id;
            }
        ]);

        foreach ($projects as $project) {
            $projectUsers = $users->random(rand(3, 4));
            $project->users()->attach($projectUsers, ['role' => 'member']);
            if (!$project->users->contains($project->owner_id)) {
                $project->users()->attach($project->owner_id, ['role' => 'owner']);
            }
        }

        $tasks = Task::factory(8)->create([
            'project_id' => function() use ($projects) {
                return $projects->random()->id;
            },
            'author_id' => function() use ($users) {
                return $users->random()->id;
            },
            'assignee_id' => function() use ($users) {
                return $users->random()->id;
            }
        ]);

        Comment::factory(6)->create([
            'task_id' => function() use ($tasks) {
                return $tasks->random()->id;
            },
            'author_id' => function() use ($users) {
                return $users->random()->id;
            }
        ]);
    }
}
