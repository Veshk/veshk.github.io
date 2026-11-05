<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalog = json_decode(file_get_contents(__DIR__.'/data/school-programs.json'), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($catalog): void {
            foreach ($catalog['records'] as $source) {
                $program = Program::where('source_id', $source['source_id'])->first() ?? Program::where('tag', $source['tag'])->first();
                if (! $program) {
                    $matches = Program::whereNull('source_id')->where('title', $source['title'])->get();
                    $program = $matches->count() === 1 ? $matches->first() : new Program;
                }
                $template = $program->template_key ?? $source['template_key'];
                $program->fill([...$source, 'template_key' => $template])->save();
            }
        });
    }
}
