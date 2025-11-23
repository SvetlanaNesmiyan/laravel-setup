<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Task;
use App\Models\User;

class CommentFactory extends Factory
{
    protected $model = \App\Models\Comment::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'author_id' => User::factory(),
            'body' => $this->faker->paragraph(),
        ];
    }
}
