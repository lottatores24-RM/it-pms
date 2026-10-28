<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-2 months', '+1 month')->setTime(0, 0);

        return [
            'name' => 'Sistem '.fake()->words(2, true),
            'code' => 'PRJ-'.fake()->unique()->numerify('####'),
            'description' => fake()->paragraph(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween($startDate, (clone $startDate)->modify('+6 months'))->format('Y-m-d'),
            'status' => ProjectStatus::Planning,
            'sponsor' => fake()->company(),
            'created_by' => User::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::InProgress,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Closed,
            'closing_notes' => fake()->sentence(),
            'closed_at' => now(),
        ]);
    }
}
