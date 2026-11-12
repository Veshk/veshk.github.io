<?php

namespace Database\Factories;

use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SelfStudyEnrollment>
 */
class SelfStudyEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'self_study_course_id' => SelfStudyCourse::factory(), 'starts_at' => now(), 'expires_at' => now()->addMonth(), 'completed_lessons' => [], 'notes' => [], 'history' => []];
    }
}
