<?php

namespace Database\Seeders;

use App\Models\AcademicAdvisor;
use App\Models\Enrollment;
use App\Models\EnrollmentApplication;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Демонстрационные данные разрешены только в local/testing.');
        }
        $sample = json_decode(file_get_contents(__DIR__.'/data/portal-directory.json'), true, flags: JSON_THROW_ON_ERROR);
        $courseContent = json_decode(file_get_contents(__DIR__.'/data/course-content.json'), true, flags: JSON_THROW_ON_ERROR);
        $advisors = [];
        foreach ($courseContent['academicAdvisors'] ?? [] as $key => $profile) {
            $advisors[$key] = AcademicAdvisor::firstOrCreate(['name' => $profile['name']], ['email' => $profile['email'] ?: null, 'photo_url' => $profile['photo'] ?: null]);
        }
        $users = [];
        foreach ($sample['staff'] as $key => $person) {
            $profile = $courseContent['staffProfiles'][$key] ?? [];
            $users[$key] = User::firstOrCreate(['email' => $person['email']], ['name' => $courseContent['staffNames'][$key] ?? $person['name'], 'password' => Hash::make('VeshDemo2026!'), 'contact_text' => $profile['text'] ?? null, 'contact_url' => $profile['contact_url'] ?? null]);
            $users[$key]->forceFill(['role' => $person['role'], 'reviews_unassigned' => in_array($key, ['admin', 'senior'], true)])->save();
        }
        $programs = [];
        foreach ($sample['programs'] as $key => $program) {
            $programs[$key] = Program::firstOrCreate(['tag' => $program['tag']], ['title' => $program['title'], 'section' => $program['section'] ?: null, 'template_key' => $program['template_key'] ?? 'short', 'cover_url' => $program['cover_url'] ?? null]);
        }
        $this->call(SchoolProgramSeeder::class);
        $groups = [];
        $schedules = json_decode(file_get_contents(__DIR__.'/data/school-schedules.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($sample['groups'] as $key => $group) {
            $blocks = CoursePage::defaults('short');
            $source = $schedules['groups'][$key] ?? ['lessons' => [], 'pending_lessons' => []];
            foreach ($blocks as &$block) {
                if ($block['type'] === 'schedule') {
                    $block['data']['lessons'] = $source['lessons'];
                }
            }
            unset($block);
            if ($source['pending_lessons']) {
                $blocks[] = ['type' => 'info', 'data' => ['block_id' => 'school-undated', 'title' => 'Занятия без даты', 'visible' => true, 'text' => "Дата и время будут уточнены администратором.\n\n".implode("\n", array_map(fn ($lesson) => $lesson['title'].' — '.$lesson['teacher'].' ('.$lesson['hours'].' ак. ч.)', $source['pending_lessons']))]];
            }
            $content = $courseContent['groups'][$key] ?? [];
            // Preserve source tags, including legacy tags that differ from the automatic convention.
            $groups[$key] = StudyGroup::withoutEvents(fn () => StudyGroup::firstOrCreate(['code' => $group['code']], [
                'title' => $group['name'], 'number' => $group['number'], 'program_id' => $programs[$group['program_id']]->id,
                'administrator_id' => $users[$group['id']]->id, 'format' => $group['format'], 'group_type' => $group['group_type'],
                'status' => $group['status'], 'starts_at' => $group['starts_at'], 'graduates_at' => $group['graduates_at'],
                'access_days' => 30, 'registration_open' => $group['registration_open'] ?? true, 'page_template' => 'short', 'page_blocks' => $content['page_blocks'] ?? $blocks,
                'extended_until' => $group['extended_until'] ?? null,
                'academic_advisor_id' => isset($content['academic_advisor_id']) ? $advisors[$content['academic_advisor_id']]->id : null,
                'chat_url' => $content['chat_url'] ?? null,
                'description' => $content['description'] ?? 'Расписание, записи и материалы вашей группы собраны здесь. По вопросам обучения обращайтесь к администратору.',
            ]));
        }
        $names = ['Анна Петрова', 'Иван Соловьёв', 'Софья Новикова', 'Михаил Орлов', 'Анастасия Белова', 'Дмитрий Смирнов', 'Екатерина Соколова', 'Александр Волков', 'Марина Кузьмина', 'Андрей Лебедев', 'Ольга Фролова', 'Павел Крылов'];
        foreach (array_values($groups) as $i => $group) {
            $student = User::firstOrCreate(['email' => $i === 0 ? 'student@vesh.test' : 'student-'.($i + 1).'@vesh.test'], ['name' => $names[$i] ?? 'Тестовый слушатель '.($i + 1), 'password' => Hash::make('VeshDemo2026!')]);
            Enrollment::firstOrCreate(['user_id' => $student->id, 'study_group_id' => $group->id]);
        }
        $demoStudent = User::where('email', 'student@vesh.test')->firstOrFail();
        foreach (['kd26', 'mba'] as $groupKey) {
            Enrollment::firstOrCreate(['user_id' => $demoStudent->id, 'study_group_id' => $groups[$groupKey]->id]);
        }
        foreach ([['waiting', 'Илья Морозов', $groups['mba']->id], ['unknown', 'Наталья Соколова', null], ['other', 'Олег Крылов', $groups['excel']->id]] as [$key, $name, $groupId]) {
            $student = User::firstOrCreate(['email' => $key.'@vesh.test'], ['name' => $name, 'password' => Hash::make('VeshDemo2026!')]);
            EnrollmentApplication::firstOrCreate(['user_id' => $student->id, 'study_group_id' => $groupId], ['message' => 'Тестовая заявка. Помогите проверить группу и открыть доступ.']);
        }
    }
}
