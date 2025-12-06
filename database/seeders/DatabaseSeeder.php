<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Очищаємо таблиці
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('comments')->truncate();
        DB::table('tasks')->truncate();
        DB::table('project_user')->truncate();
        DB::table('projects')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $users = collect([
            User::create([
                'name' => 'Адміністратор Системи',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]),
            User::create([
                'name' => 'Олександр Петренко',
                'email' => 'olexander@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]),
            User::create([
                'name' => 'Марія Коваленко',
                'email' => 'maria@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]),
            User::create([
                'name' => 'Іван Сидоренко',
                'email' => 'ivan@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]),
            User::create([
                'name' => 'Наталія Шевченко',
                'email' => 'natalia@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]),
        ]);

        $users = $users->merge(User::factory(3)->create());

        $projects = collect([
            Project::create([
                'owner_id' => $users[0]->id,
                'name' => 'Розробка CRM-системи',
            ]),
            Project::create([
                'owner_id' => $users[1]->id,
                'name' => 'Веб-сайт компанії',
            ]),
            Project::create([
                'owner_id' => $users[2]->id,
                'name' => 'Мобільний додаток',
            ]),
            Project::create([
                'owner_id' => $users[3]->id,
                'name' => 'Впровадження ERP',
            ]),
            Project::create([
                'owner_id' => $users[4]->id,
                'name' => 'Маркетингова кампанія',
            ]),
        ]);

        foreach ($projects as $project) {
            $projectUsers = $users->random(rand(3, 4));

            foreach ($projectUsers as $user) {
                $role = ($user->id === $project->owner_id) ? 'owner' : 'member';
                $project->users()->attach($user->id, ['role' => $role]);
            }
        }

        $tasks = collect();

        foreach ($projects as $project) {
            $projectTasks = Task::factory(rand(5, 10))->create([
                'project_id' => $project->id,
                'author_id' => function() use ($project) {
                    return $project->users->random()->id;
                },
                'assignee_id' => function() use ($project) {
                    return $project->users->random()->id;
                },
            ]);

            $tasks = $tasks->merge($projectTasks);
        }

        foreach ($tasks as $task) {
            $commentCount = rand(1, 5);

            for ($i = 0; $i < $commentCount; $i++) {
                Comment::create([
                    'task_id' => $task->id,
                    'author_id' => $task->project->users->random()->id,
                    'body' => $this->generateUkrainianComment(),
                ]);
            }
        }

        $this->command->info('Базу даних успішно заповнено тестовими даними!');
        $this->command->info('Кількість користувачів: ' . $users->count());
        $this->command->info('Кількість проєктів: ' . $projects->count());
        $this->command->info('Кількість задач: ' . $tasks->count());
        $this->command->info('Тестові облікові записи:');
        $this->command->info('1. admin@example.com / password123');
        $this->command->info('2. olexander@example.com / password123');
        $this->command->info('3. maria@example.com / password123');
    }

    private function generateUkrainianComment(): string
    {
        $comments = [
            'Ця задача потребує уваги. Термін виконання наближається.',
            'Чудова робота! Все виконано вчасно та якісно.',
            'Потрібно обговорити деталі з командою перед продовженням.',
            'Є декілька зауважень щодо реалізації. Обговоримо на наступній зустрічі.',
            'Всі необхідні матеріали завантажено. Можна продовжувати роботу.',
            'Виникли технічні складнощі. Потрібна допомога спеціаліста.',
            'Задача виконана на 100%. Всі критерії виконано.',
            'Потрібно перевірити сумісність з іншими модулями системи.',
            'Клієнт вніс корективи. Потрібно внести зміни у вимоги.',
            'Тестування пройшло успішно. Можна переходити до наступного етапу.',
        ];

        return $comments[array_rand($comments)];
    }
}
