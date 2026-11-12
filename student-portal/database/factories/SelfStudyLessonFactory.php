<?php

namespace Database\Factories;

use App\Models\SelfStudyCourse;
use App\Models\SelfStudyLesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SelfStudyLesson>
 */
class SelfStudyLessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['self_study_course_id' => SelfStudyCourse::factory(), 'title' => 'Урок курса', 'position' => 1, 'video_url' => 'https://kinescope.io/testVideo123', 'is_published' => true];
    }
}
