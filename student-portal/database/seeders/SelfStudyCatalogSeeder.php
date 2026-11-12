<?php

namespace Database\Seeders;

use App\Models\SelfStudyCourse;
use Illuminate\Database\Seeder;

class SelfStudyCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = json_decode(file_get_contents(__DIR__.'/data/self-study-catalog.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($courses as $data) {
            $lessons = $data['lessons'];
            unset($data['lessons']);
            $course = SelfStudyCourse::firstOrCreate(['slug' => $data['slug']], [...$data, 'is_published' => false]);
            if ($course->wasRecentlyCreated) {
                $course->lessons()->createMany($lessons);
            }
        }
    }
}
