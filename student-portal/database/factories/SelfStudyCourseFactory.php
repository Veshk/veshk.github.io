<?php

namespace Database\Factories;

use App\Models\SelfStudyCourse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SelfStudyCourse>
 */
class SelfStudyCourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['title' => 'Учебный курс', 'slug' => fake()->unique()->slug(), 'kind' => 'free', 'administrator_id' => User::factory()->state(['role' => 'admin']), 'is_published' => true, 'access_days' => 90, 'price_minor' => 300000];
    }
}
