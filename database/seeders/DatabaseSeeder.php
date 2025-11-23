<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Створюємо 8 користувачів
        $users = User::factory(8)->create();

        // Додаємо тестового користувача
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Створюємо 5 проектів
        $projects = Project::factory(5)->create();

        // Додаємо користувачів до проектів (зв'язок many-to-many)
        foreach ($projects as $project) {
            $project->users()->attach(
                $users->random(3)->pluck('id'),
                ['role' => 'member']
            );
        }

        // Створюємо 20 задач
        $tasks = Task::factory(20)->create();

        // Створюємо 60 коментарів (по 3 на задачу)
        Comment::factory(60)->create();
    }
}
