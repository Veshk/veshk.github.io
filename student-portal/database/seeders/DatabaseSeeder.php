<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\EnrollmentApplication;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Демонстрационные данные разрешены только в local/testing.');
        }
        $people = [
            'senior' => ['Елена Волкова', 'senior', true], 'admin' => ['Мария Белова', 'admin', true],
            'colleague' => ['Ольга Орлова', 'admin', false], 'student' => ['Анна Петрова', 'student', false],
            'waiting' => ['Илья Морозов', 'student', false], 'unknown' => ['Наталья Соколова', 'student', false],
            'other' => ['Олег Крылов', 'student', false],
        ];
        $users = [];
        foreach ($people as $key => [$name,$role,$review]) {
            $users[$key] = User::firstOrCreate(['email' => $key.'@vesh.test'], ['name' => $name, 'password' => Hash::make('VeshDemo2026!')]);
            $users[$key]->forceFill(['role' => $role, 'reviews_unassigned' => $review])->save();
        }
        $catalog = [];
        foreach ([
            ['ДЕМО_КД', 'MBA Коммерческий директор', 'Продажи'],
            ['ДЕМО_EXCEL', 'Excel для эффективной работы', 'Аналитика'],
            ['ДЕМО_МЕН', 'Профессиональные навыки управления людьми', 'Упр. навыки'],
        ] as [$tag, $title, $section]) {
            $catalog[$tag] = Program::firstOrCreate(['tag' => $tag], ['title' => $title, 'section' => $section]);
        }
        foreach (['КД', 'EXCEL', 'МЕН'] as $prefix) {
            $suffix = $prefix === 'МЕН' ? '2027/01' : '2026/11';
            if (! StudyGroup::where('code', $prefix.' '.$suffix)->exists()) {
                StudyGroup::where('code', $prefix." \u{00B7} ".$suffix)->update(['code' => $prefix.' '.$suffix]);
            }
        }
        $start = now()->addDays(4);
        $mba = StudyGroup::firstOrCreate(['code' => 'КД 2026/11'], [
            'title' => 'MBA Коммерческий директор', 'format' => 'Очно', 'administrator_id' => $users['admin']->id,
            'registration_open' => true, 'starts_at' => $start->toDateString(), 'graduates_at' => $start->copy()->addMonths(15)->toDateString(),
            'description' => 'Развивайте управленческое мышление, делитесь опытом и находите решения для своего бизнеса. Все материалы вашей группы — здесь.',
            'schedule' => [
                ['date' => $start->format('d.m.Y'), 'time' => '10:00–18:00', 'title' => 'Бизнес и организация как система', 'subtitle' => 'Модуль 1 — Стратегия и управление', 'teacher' => 'Алексей Миронов'],
                ['date' => $start->copy()->addDay()->format('d.m.Y'), 'time' => '10:00–18:00', 'title' => 'Модель устойчивой организации', 'subtitle' => 'Практика — Работа в малых группах', 'teacher' => 'Алексей Миронов'],
                ['date' => $start->copy()->addDays(2)->format('d.m.Y'), 'time' => '10:00–17:00', 'title' => 'Команда и управленческое взаимодействие', 'subtitle' => 'Тренинг — Управление людьми', 'teacher' => 'Ирина Лебедева'],
            ],
            'materials' => [['title' => 'Как подготовиться к первому модулю'], ['title' => 'Рабочая тетрадь: системный взгляд на бизнес']],
        ]);
        $excel = StudyGroup::firstOrCreate(['code' => 'EXCEL 2026/11'], [
            'title' => 'Excel для эффективной работы', 'format' => 'Онлайн', 'administrator_id' => $users['colleague']->id,
            'registration_open' => true, 'starts_at' => $start->toDateString(), 'graduates_at' => $start->copy()->addMonth()->toDateString(),
            'description' => 'Практические инструменты для работы с данными.', 'materials' => [['title' => 'Подготовка рабочего места']],
        ]);
        $management = StudyGroup::firstOrCreate(['code' => 'МЕН 2027/01'], [
            'title' => 'Профессиональные навыки управления людьми', 'format' => 'Очно', 'administrator_id' => $users['senior']->id,
            'registration_open' => false, 'starts_at' => now()->addMonths(2)->toDateString(), 'graduates_at' => now()->addMonths(8)->toDateString(),
        ]);
        foreach ([[$mba, 'ДЕМО_КД', '2026/11'], [$excel, 'ДЕМО_EXCEL', '2026/11'], [$management, 'ДЕМО_МЕН', '2027/01']] as [$group, $tag, $number]) {
            $group->fill([
                'program_id' => $group->program_id ?? $catalog[$tag]->id,
                'number' => $group->number ?? $number,
                'status' => $group->status ?? 'Запускается',
                'group_type' => $group->group_type ?? ($group->format === 'Онлайн' ? 'Сборное ОНЛАЙН' : 'Сборное ОЧНО'),
            ])->save();
        }
        Enrollment::firstOrCreate(['user_id' => $users['student']->id, 'study_group_id' => $mba->id]);
        EnrollmentApplication::firstOrCreate(['user_id' => $users['waiting']->id, 'study_group_id' => $mba->id], ['message' => 'Учусь в ноябрьской группе. Договор оформлен.']);
        EnrollmentApplication::firstOrCreate(['user_id' => $users['unknown']->id, 'study_group_id' => null], ['message' => 'Поступила на программу по управлению. Не помню номер группы. Помогите, пожалуйста.']);
        EnrollmentApplication::firstOrCreate(['user_id' => $users['other']->id, 'study_group_id' => $excel->id], ['message' => 'Корпоративная группа, онлайн.']);
    }
}
