<?php

namespace Database\Factories;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'study_group_id' => StudyGroup::factory()];
    }
}
