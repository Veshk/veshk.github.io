<?php

namespace Database\Factories;

use App\Models\CourseOrder;
use App\Models\SelfStudyCourse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseOrder>
 */
class CourseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'self_study_course_id' => SelfStudyCourse::factory()->state(['kind' => 'recorded']), 'amount_minor' => 300000, 'access_days' => 90, 'currency' => 'RUB', 'status' => 'pending'];
    }
}
