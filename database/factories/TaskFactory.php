<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Project;
use App\Models\User;

class TaskFactory extends Factory
{
    protected $model = \App\Models\Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'author_id' => User::factory(),
            'assignee_id' => User::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['todo', 'in_progress', 'done']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 month'),
        ];
    }
}
