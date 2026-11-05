<?php

namespace Database\Seeders;

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
        $users = [];
        foreach ($sample['staff'] as $key => $person) {
            $users[$key] = User::firstOrCreate(['email' => $person['email']], ['name' => $person['name'], 'password' => Hash::make('VeshDemo2026!')]);
            $users[$key]->forceFill(['role' => $person['role'], 'reviews_unassigned' => in_array($key, ['admin', 'senior'], true)])->save();
        }
        $programs = [];
        foreach ($sample['programs'] as $key => $program) {
            $programs[$key] = Program::firstOrCreate(['tag' => $program['tag']], ['title' => $program['title'], 'section' => $program['section'] ?: null, 'template_key' => 'short']);
        }
        $groups = [];
        foreach ($sample['groups'] as $key => $group) {
            // Preserve source tags, including legacy tags that differ from the automatic convention.
            $groups[$key] = StudyGroup::withoutEvents(fn () => StudyGroup::firstOrCreate(['code' => $group['code']], [
                'title' => $group['name'], 'number' => $group['number'], 'program_id' => $programs[$group['program_id']]->id,
                'administrator_id' => $users[$group['id']]->id, 'format' => $group['format'], 'group_type' => $group['group_type'],
                'status' => $group['status'], 'starts_at' => $group['starts_at'], 'graduates_at' => $group['graduates_at'],
                'access_days' => 30, 'registration_open' => true, 'page_template' => 'short', 'page_blocks' => CoursePage::defaults('short'),
            ]));
        }
        $names = ['Анна Петрова', 'Иван Соловьёв', 'Софья Новикова', 'Михаил Орлов', 'Анастасия Белова', 'Дмитрий Смирнов', 'Екатерина Соколова', 'Александр Волков', 'Марина Кузьмина', 'Андрей Лебедев', 'Ольга Фролова', 'Павел Крылов'];
        foreach (array_values($groups) as $i => $group) {
            $student = User::firstOrCreate(['email' => $i === 0 ? 'student@vesh.test' : 'student-'.($i + 1).'@vesh.test'], ['name' => $names[$i], 'password' => Hash::make('VeshDemo2026!')]);
            Enrollment::firstOrCreate(['user_id' => $student->id, 'study_group_id' => $group->id]);
        }
        foreach ([['waiting', 'Илья Морозов', $groups['mba']->id], ['unknown', 'Наталья Соколова', null], ['other', 'Олег Крылов', $groups['excel']->id]] as [$key, $name, $groupId]) {
            $student = User::firstOrCreate(['email' => $key.'@vesh.test'], ['name' => $name, 'password' => Hash::make('VeshDemo2026!')]);
            EnrollmentApplication::firstOrCreate(['user_id' => $student->id, 'study_group_id' => $groupId], ['message' => 'Тестовая заявка. Помогите проверить группу и открыть доступ.']);
        }
    }
}
