<?php

namespace App\Console\Commands;

use App\Models\SelfStudyCourse;
use Illuminate\Console\Command;

class PrepareIntegrationSandbox extends Command
{
    protected $signature = 'portal:prepare-sandbox';

    protected $description = 'Подготовить единственный тестовый курс без копирования слушателей';

    public function handle(): int
    {
        if (! config('course_integration.sandbox') || config('course_integration.course_slug') !== 'test-power-query') {
            $this->error('Команда доступна только для изолированного тестового ЛК');

            return self::FAILURE;
        }
        $catalog = json_decode(file_get_contents(database_path('seeders/data/self-study-catalog.json')), true, flags: JSON_THROW_ON_ERROR);
        $data = collect($catalog)->firstWhere('slug', 'power-query');
        $lessons = $data['lessons'];
        unset($data['lessons']);
        $data['slug'] = 'test-power-query';
        $data['title'] = 'Power Query — тест нового ЛК';
        $course = SelfStudyCourse::firstOrCreate(['slug' => $data['slug']], [...$data, 'is_published' => true]);
        if ($course->wasRecentlyCreated) {
            $course->lessons()->createMany(array_map(fn (array $lesson): array => [...$lesson, 'is_published' => true], $lessons));
        }
        $this->info('Тестовый курс готов. Слушатели и доступы не копировались.');

        return self::SUCCESS;
    }
}
