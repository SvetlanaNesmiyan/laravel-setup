<?php

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
        // Для PostgreSQL використовуємо TRUNCATE з CASCADE
        DB::statement('TRUNCATE TABLE comments CASCADE');
        DB::statement('TRUNCATE TABLE tasks CASCADE');
        DB::statement('TRUNCATE TABLE project_user CASCADE');
        DB::statement('TRUNCATE TABLE projects CASCADE');
        DB::statement('TRUNCATE TABLE users CASCADE');

        // Створюємо 8 користувачів
        $users = User::factory(8)->create();

        // Створюємо 5 проектів
        $projects = Project::factory(5)->create();

        // Додаємо користувачів до проектів (3-4 користувачі на проект)
        foreach ($projects as $project) {
            $projectUsers = $users->random(rand(3, 4));
            foreach ($projectUsers as $user) {
                $project->users()->attach($user->id, [
                    'role' => $user->id === $project->owner_id ? 'owner' : 'member'
                ]);
            }
        }

        // Створюємо 8 задач
        $tasks = Task::factory(8)->create();

        // Створюємо 5-8 коментарів
        Comment::factory(rand(5, 8))->create();
    }
}
