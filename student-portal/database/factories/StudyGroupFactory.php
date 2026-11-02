<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudyGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Демонстрационная программа', 'code' => fake()->unique()->bothify('ГР-####-??'),
            'program_id' => Program::factory(), 'status' => 'Запускается',
            'administrator_id' => User::factory()->state(['role' => 'admin']),
            'registration_open' => true, 'starts_at' => now()->toDateString(), 'graduates_at' => now()->addMonths(3)->toDateString(), 'access_days' => 30,
        ];
    }
}
