<?php

namespace Database\Factories;

use App\Enums\ChangeRequestPriority;
use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'reason' => fake()->sentence(),
            'impact_scope' => fake()->optional()->sentence(),
            'impact_schedule' => fake()->optional()->sentence(),
            'impact_resource' => fake()->optional()->sentence(),
            'priority' => fake()->randomElement(ChangeRequestPriority::cases()),
            'status' => ChangeRequestStatus::Submitted,
            'requested_by' => User::factory(),
        ];
    }
}
