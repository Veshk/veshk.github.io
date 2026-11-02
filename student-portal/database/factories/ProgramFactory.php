<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => 'Тестовая программа', 'tag' => fake()->unique()->bothify('ТЕСТ-####-??'), 'section' => 'Менеджмент'];
    }
}
