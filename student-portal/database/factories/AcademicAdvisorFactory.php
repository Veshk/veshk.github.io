<?php

namespace Database\Factories;

use App\Models\AcademicAdvisor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicAdvisor>
 */
class AcademicAdvisorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
        ];
    }
}
