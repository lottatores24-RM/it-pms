<?php

namespace Database\Factories;

use App\Enums\RiskRating;
use App\Enums\RiskResponse;
use App\Enums\RiskStatus;
use App\Models\Project;
use App\Models\Risk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Risk>
 */
class RiskFactory extends Factory
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
            'category' => fake()->randomElement(['Teknis', 'Jadwal', 'Sumber Daya', 'Eksternal']),
            'probability' => fake()->randomElement(RiskRating::cases()),
            'impact' => fake()->randomElement(RiskRating::cases()),
            'response_plan' => fake()->randomElement(RiskResponse::cases()),
            'status' => RiskStatus::Open,
        ];
    }
}
