<?php

namespace Database\Seeders;

use App\Models\AcademicAdvisor;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PublishedCourseSeeder extends Seeder
{
    public function run(): void
    {
        $directory = json_decode(file_get_contents(__DIR__.'/data/portal-directory.json'), true, flags: JSON_THROW_ON_ERROR);
        $content = json_decode(file_get_contents(__DIR__.'/data/course-content.json'), true, flags: JSON_THROW_ON_ERROR);
        $catalog = json_decode(file_get_contents(__DIR__.'/data/school-programs.json'), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($directory, $content, $catalog): void {
            $administrator = User::where('email', 'dasha.veshk@gmail.com')->first();
            if ($administrator && ! $administrator->isStaff()) {
                throw new RuntimeException('Почта администратора занята учётной записью слушателя. Перенос отменён.');
            }
            if (! $administrator) {
                $profile = $content['staffProfiles']['admin'];
                $administrator = new User;
                $administrator->forceFill([
                    'name' => $directory['groups']['mba']['owner'],
                    'email' => 'dasha.veshk@gmail.com',
                    'password' => Str::random(64),
                    'role' => 'admin',
                    'reviews_unassigned' => false,
                    'contact_text' => $profile['text'],
                    'contact_url' => $profile['contact_url'],
                ])->save();
            }

            foreach (['mba' => 'ОНЛ_СПО', 'kd26' => 'МБАКД'] as $key => $programTag) {
                $source = $directory['groups'][$key];
                if (StudyGroup::where('code', $source['code'])->exists()) {
                    continue;
                }
                $page = $content['groups'][$key];
                $programSource = collect($catalog['records'])->firstWhere('tag', $programTag);
                $program = Program::where('source_id', $programSource['source_id'])->first()
                    ?? Program::firstOrCreate(['tag' => $programTag], $programSource);
                $advisor = null;
                if (isset($page['academic_advisor_id'])) {
                    $profile = $content['academicAdvisors'][$page['academic_advisor_id']];
                    $advisor = AcademicAdvisor::firstOrCreate(['name' => $profile['name']], [
                        'email' => $profile['email'], 'photo_url' => $profile['photo'],
                    ]);
                }

                StudyGroup::withoutEvents(fn () => StudyGroup::create([
                    'code' => $source['code'], 'title' => $source['name'], 'number' => $source['number'],
                    'program_id' => $program->id, 'administrator_id' => $administrator->id,
                    'format' => $source['format'], 'group_type' => $source['group_type'],
                    'status' => $source['status'], 'starts_at' => $source['starts_at'], 'graduates_at' => $source['graduates_at'],
                    'access_days' => $source['access_days'], 'extended_until' => $source['extended_until'] ?? null,
                    'registration_open' => $source['registration_open'], 'setup_completed' => true,
                    'page_template' => $programSource['template_key'], 'page_blocks' => $page['page_blocks'],
                    'academic_advisor_id' => $advisor?->id, 'chat_url' => $page['chat_url'],
                    'description' => $page['description'],
                ]));
            }
        });
    }
}
