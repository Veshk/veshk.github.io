<?php

namespace Tests\Feature;

use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\StudyGroups\Pages\CreateStudyGroup;
use App\Filament\Resources\StudyGroups\Pages\EditGroupPage;
use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use App\Support\GroupHero;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CoursePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_group_copies_program_template_and_later_edits_do_not_cross_groups(): void
    {
        $original = [['type' => 'info', 'data' => ['title' => 'База', 'text' => 'Исходный текст', 'visible' => true]]];
        $program = Program::factory()->create(['tag' => 'КД', 'template_key' => 'corporate', 'page_blocks' => $original]);
        $first = StudyGroup::factory()->for($program)->create(['number' => '01']);
        $second = StudyGroup::factory()->for($program)->create(['number' => '02']);
        $this->assertSame($original, $first->page_blocks);
        $this->assertSame('corporate', $first->page_template);
        $program->update(['page_blocks' => []]);
        $first->update(['page_blocks' => [['type' => 'info', 'data' => ['title' => 'Своя страница']]]]);
        $this->assertSame($original, $second->fresh()->page_blocks);
        $this->assertSame([], StudyGroup::factory()->for($program)->create(['number' => '03'])->page_blocks);
    }

    public function test_group_tag_is_generated_and_duplicate_number_does_not_create_group(): void
    {
        $senior = User::factory()->create(['role' => 'senior']);
        $program = Program::factory()->create(['tag' => 'ОНЛ_КД/А']);
        $this->actingAs($senior);
        $data = ['program_id' => $program->id, 'title' => 'Тестовая группа', 'code' => 'Подмена', 'number' => '26-2', 'administrator_id' => $senior->id, 'status' => 'Запускается', 'format' => 'Очно', 'starts_at' => '2026-11-10', 'graduates_at' => '2027-11-10', 'access_days' => 30];
        Livewire::test(CreateStudyGroup::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('study_groups', ['code' => 'ОНЛ_КД/А-26-2', 'number' => '26-2']);
        Livewire::test(CreateStudyGroup::class)->fillForm($data)->call('create')->assertHasFormErrors(['number']);
        $this->assertDatabaseCount('study_groups', 1);
        Livewire::test(CreateStudyGroup::class)->fillForm(array_replace($data, ['number' => '']))->call('create')->assertHasFormErrors(['number' => 'required']);
    }

    public function test_old_tags_and_group_links_survive_unrelated_edits(): void
    {
        $group = StudyGroup::factory()->create(['code' => 'Старый/ТЕГ', 'number' => null]);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $group->update(['description' => 'Новое приветствие']);
        $this->assertSame('Старый/ТЕГ', $group->fresh()->code);
        $group->update(['number' => '27']);
        $this->assertSame($group->program->tag.'-27', $group->fresh()->code);
        $this->assertSame($group->id, $enrollment->fresh()->study_group_id);
        $group->program->update(['tag' => 'НОВЫЙ']);
        $group->update(['description' => 'Ещё правка']);
        $this->assertNotSame('НОВЫЙ-27', $group->fresh()->code);
    }

    public function test_page_editor_uses_group_permissions_and_saves_only_page_content(): void
    {
        $group = StudyGroup::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/study-groups/'.$group->id.'/page')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/study-groups/'.$group->id.'/page')->assertForbidden();
        $this->actingAs($group->administrator);
        $this->get('/admin/study-groups/'.$group->id.'/page')->assertOk()->assertSee('Редактировать страницу ЛК');
        $blocks = [['type' => 'info', 'data' => ['block_id' => 'own-block', 'title' => 'Свой блок', 'text' => 'Текст', 'visible' => true, 'url' => null, 'button' => null]]];
        $editor = Livewire::test(EditGroupPage::class, ['record' => $group->id]);
        $savedHash = $editor->get('savedDataHash');
        $editor->fillForm(['page_blocks' => $blocks])->assertSet('savedDataHash', $savedHash)->call('save')->assertHasNoFormErrors();
        $this->assertNotSame($savedHash, $editor->get('savedDataHash'));
        $this->assertSame($blocks, $group->fresh()->page_blocks);
        $this->assertSame($group->administrator_id, $group->fresh()->administrator_id);
        $this->actingAs(User::factory()->create(['role' => 'senior']))->get('/admin/study-groups/'.$group->id.'/page')->assertOk();
    }

    public function test_save_and_open_validates_recordings_before_redirecting_and_preserves_details(): void
    {
        $video = ['title' => 'Разбор кейса', 'date' => '2026-11-16', 'status' => 'published', 'url' => 'https://example.com/recording', 'module' => 'Модуль 1', 'text' => 'Описание занятия'];
        $blocks = [['type' => 'recordings', 'data' => ['block_id' => 'videos', 'title' => 'Записи занятий', 'visible' => true, 'videos' => [$video]]]];
        $group = StudyGroup::factory()->create(['page_blocks' => $blocks]);
        $this->actingAs($group->administrator);
        $blocks[0]['data']['videos'][0]['title'] = 'Уточнённая тема';

        Livewire::test(EditGroupPage::class, ['record' => $group->id])
            ->assertSee('Блоки для редактирования')->assertSee('16.11.2026 Опубликована Разбор кейса')
            ->set('block', 'videos')->fillForm(['page_blocks' => $blocks])
            ->callAction(TestAction::make('saveAndOpen')->schemaComponent('form-actions', 'content'))->assertHasNoFormErrors()
            ->assertRedirect(route('student.group', $group).'#course-block-videos');
        $this->assertSame($blocks, $group->fresh()->page_blocks);

        $blocks[0]['data']['videos'][0]['url'] = null;
        Livewire::test(EditGroupPage::class, ['record' => $group->id])
            ->fillForm(['page_blocks' => $blocks])->callAction(TestAction::make('saveAndOpen')->schemaComponent('form-actions', 'content'))
            ->assertHasFormErrors()->assertNoRedirect();
        $this->assertSame('https://example.com/recording', $group->fresh()->page_blocks[0]['data']['videos'][0]['url']);

        Livewire::test(EditGroupPage::class, ['record' => $group->id])
            ->call('save')->assertHasNoFormErrors()->assertNoRedirect();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->assertForbidden();
    }

    public function test_student_page_renders_visible_blocks_in_order_and_escapes_links_and_text(): void
    {
        $group = StudyGroup::factory()->create(['page_blocks' => [
            ['type' => 'info', 'data' => ['title' => 'Второй блок первым', 'text' => '<script>alert(1)</script>', 'url' => 'javascript:alert(1)', 'visible' => true]],
            ['type' => 'info', 'data' => ['title' => 'Скрытый блок', 'visible' => false]],
            ['type' => 'materials', 'data' => ['title' => 'Папка группы', 'url' => 'https://example.com/folder', 'visible' => true]],
        ]]);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertOk()->assertSeeInOrder(['Второй блок первым', 'Папка группы'])->assertDontSee('Скрытый блок')->assertDontSee('javascript:alert(1)', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false)->assertSee('https://example.com/folder')->assertSee('target="_blank"', false);
    }

    public function test_collapsed_lesson_editor_preserves_import_fields_and_rejects_invalid_time(): void
    {
        $lesson = ['source_id' => 'sheet-42', 'date' => '2026-11-16', 'time' => '10:00–13:00', 'title' => 'Занятие', 'teacher' => 'Преподаватель', 'format' => 'Очно', 'url' => 'https://example.com/lesson', 'module' => 'Модуль', 'hours' => '4'];
        $blocks = [['type' => 'schedule', 'data' => ['block_id' => 'schedule', 'title' => 'Расписание', 'visible' => true, 'show_moscow' => true, 'collapse_past' => true, 'source_url' => null, 'lessons' => [$lesson, array_replace($lesson, ['source_id' => 'sheet-43', 'time' => '14:00–17:00'])]]]];
        $group = StudyGroup::factory()->create(['page_blocks' => $blocks]);
        $this->actingAs($group->administrator);
        $blocks[0]['data']['lessons'][0]['title'] = 'Уточнённая тема';
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['page_blocks' => $blocks])->call('save')->assertHasNoFormErrors();
        $this->assertSame($blocks[0]['data']['lessons'], $group->fresh()->page_blocks[0]['data']['lessons']);
        $blocks[0]['data']['lessons'][0]['time'] = '25:00–30:00';
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['page_blocks' => $blocks])->call('save')->assertHasFormErrors();
        $this->assertSame('10:00–13:00', $group->fresh()->page_blocks[0]['data']['lessons'][0]['time']);
    }

    public function test_legacy_contacts_render_safe_email_phone_and_multiple_chats_without_duplicate_name(): void
    {
        $group = StudyGroup::factory()->make();
        $group->setRelation('administrator', User::factory()->make([
            'name' => 'Дарья Маврина',
            'contact_text' => "  Дарья   Маврина\nemail: dasha+group@example.com\nтел.: 8-999-286-67-07\nГрафик работы: пн-пт с 10:00 до 18:00\n<script>alert(1)</script>",
            'contact_url' => 'https://max.ru/admin',
        ]));
        $html = view('student.page-block', ['type' => 'contacts', 'group' => $group, 'data' => ['chats' => [
            ['label' => 'MAX', 'url' => 'https://max.ru/group'],
            ['label' => 'Telegram', 'url' => 'https://t.me/group'],
            ['label' => 'Unsafe', 'url' => 'javascript:alert(1)'],
        ]]])->render();
        $this->assertSame(1, substr_count($html, 'Дарья Маврина'));
        foreach (['href="mailto:dasha%2Bgroup@example.com"', 'href="tel:+79992866707"', '10:00 до 18:00', 'https://max.ru/group', 'https://t.me/group', 'Написать администратору', '&lt;script&gt;'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('tel:10', $html);
    }

    public function test_legacy_schedule_is_preserved_and_moscow_time_handles_midnight(): void
    {
        $group = StudyGroup::factory()->create(['schedule' => [['date' => '06.11.2026', 'time' => '01:00–03:00', 'title' => 'Старое занятие', 'subtitle' => 'Модуль 1']]]);
        $group->update(['page_blocks' => null]);
        $blocks = CoursePage::forGroup($group);
        $this->assertSame('2026-11-06', $blocks[1]['data']['lessons'][0]['date']);
        $this->assertSame('Модуль 1', $blocks[1]['data']['lessons'][0]['module']);
        $this->assertNull($group->fresh()->page_blocks);
        $this->assertSame('23:00–01:00', CoursePage::moscow('01:00–03:00'));
    }

    public function test_editor_saves_new_blocks_and_requires_a_url_only_for_published_recordings(): void
    {
        $group = StudyGroup::factory()->create();
        $this->actingAs($group->administrator);
        $blocks = [
            ['type' => 'homework', 'data' => ['block_id' => 'tasks', 'title' => 'Задания', 'visible' => true, 'tasks' => [['module' => 'Модуль', 'deadline' => '2026-11-18', 'status' => 'upcoming', 'title' => 'Прочитать материалы', 'url' => 'https://example.com/task']]]],
            ['type' => 'faq', 'data' => ['block_id' => 'faq', 'title' => 'Вопросы', 'visible' => true, 'questions' => [['question' => 'Где материалы?', 'answer' => "В папке группы.\nСсылка ниже.", 'url' => 'https://example.com/folder']]]],
            ['type' => 'recordings', 'data' => ['block_id' => 'videos', 'title' => 'Записи', 'visible' => true, 'videos' => [['title' => 'Будущая запись', 'date' => '2026-11-16', 'status' => 'pending', 'url' => null]]]],
            ['type' => 'materials', 'data' => ['block_id' => 'resources', 'title' => 'Материалы', 'visible' => true, 'resources' => [['title' => 'Книги', 'url' => 'https://example.com/books'], ['title' => 'Задания', 'url' => 'https://example.com/tasks']]]],
            ['type' => 'introductions', 'data' => ['block_id' => 'intro', 'title' => 'Знакомство', 'visible' => true, 'image_url' => 'https://example.com/cover.jpg', 'image_alt' => 'Обложка']],
        ];
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['page_blocks' => $blocks])->call('save')->assertHasNoFormErrors();
        $saved = $group->fresh()->page_blocks;
        $this->assertSame('upcoming', $saved[0]['data']['tasks'][0]['status']);
        $this->assertSame('Где материалы?', $saved[1]['data']['questions'][0]['question']);
        $this->assertCount(2, $saved[3]['data']['resources']);
        $this->assertSame('https://example.com/cover.jpg', $saved[4]['data']['image_url']);
        $blocks[2]['data']['videos'][0]['status'] = 'published';
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['page_blocks' => $blocks])->call('save')->assertHasFormErrors();
        $this->assertSame('pending', $group->fresh()->page_blocks[2]['data']['videos'][0]['status']);
        $blocks[2]['data']['videos'][0]['url'] = 'https://example.com/video';
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['page_blocks' => $blocks])->call('save')->assertHasNoFormErrors();
        $this->assertSame('published', $group->fresh()->page_blocks[2]['data']['videos'][0]['status']);
    }

    public function test_new_blocks_render_safely_and_pending_recordings_hide_draft_links(): void
    {
        $group = StudyGroup::factory()->create(['page_blocks' => [
            ['type' => 'homework', 'data' => ['title' => 'Задания', 'tasks' => [['title' => '<script>alert(1)</script>', 'status' => 'past', 'url' => 'javascript:alert(1)']]]],
            ['type' => 'faq', 'data' => ['title' => 'Вопросы', 'questions' => [['question' => 'Как учиться?', 'answer' => 'Ответ для группы', 'url' => 'https://example.com/faq']]]],
            ['type' => 'recordings', 'data' => ['title' => 'Записи', 'videos' => [['title' => 'Будущая запись', 'status' => 'pending', 'url' => 'https://example.com/draft'], ['title' => 'Старая запись', 'url' => 'https://example.com/legacy']]]],
            ['type' => 'materials', 'data' => ['title' => 'Материалы', 'url' => 'https://example.com/folder', 'resources' => [['title' => 'Книги', 'url' => 'https://example.com/books']]]],
            ['type' => 'video', 'data' => ['title' => 'Ролик', 'image_url' => 'javascript:alert(1)']],
        ]]);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertOk()
            ->assertSee('Прошедшее')->assertSee('Видео ещё не опубликовано')->assertSee('Ответ для группы')
            ->assertSee('https://example.com/legacy')->assertSee('https://example.com/folder')->assertSee('https://example.com/books')
            ->assertDontSee('https://example.com/draft')->assertDontSee('javascript:', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_past_homework_is_collapsed_by_status_without_hiding_current_tasks(): void
    {
        $group = StudyGroup::factory()->create(['page_blocks' => [
            ['type' => 'homework', 'data' => ['tasks' => [
                ['title' => 'Архивное задание', 'status' => 'past', 'url' => 'https://example.com/past', 'button' => 'Открыть'],
                ['title' => 'Текущее задание', 'status' => 'upcoming', 'deadline' => '2000-01-01', 'url' => 'https://example.com/current', 'button' => 'Моя подпись'],
            ]]],
        ]]);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $response = $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertOk();
        $html = $response->getContent();
        preg_match('/<details class="past-homework">([\s\S]*?)<\/details>/', $html, $archive);
        $this->assertNotEmpty($archive);
        $this->assertStringContainsString('Архивное задание', $archive[1]);
        $this->assertStringContainsString('https://example.com/past', $archive[1]);
        $this->assertStringContainsString('Перейти к заданию', $archive[1]);
        $this->assertStringNotContainsString('Текущее задание', $archive[1]);
        $this->assertStringContainsString('Текущее задание', $html);
        $this->assertStringContainsString('Моя подпись', $html);
    }

    public function test_recording_dates_are_not_repeated_and_custom_copy_is_preserved(): void
    {
        $group = StudyGroup::factory()->create(['page_blocks' => [
            ['type' => 'recordings', 'data' => ['videos' => [
                ['date' => '2026-10-19', 'title' => '1 занятие 19.10.2026', 'text' => 'Запись занятия от 19.10.2026.', 'url' => 'https://example.com/one'],
                ['date' => '2026-10-26', 'title' => 'Разбор кейса — 26.10.2026', 'text' => 'Разбор решений команды', 'url' => 'https://example.com/two'],
            ]]],
            ['type' => 'materials', 'data' => ['resources' => [
                ['title' => 'Папка группы', 'url' => 'https://example.com/folder'],
                ['title' => 'Библиотека MBA', 'url' => 'https://example.com/books'],
            ]]],
            ['type' => 'faq', 'data' => ['questions' => [['question' => 'Мой вопрос', 'answer' => 'Мой ответ']]]],
        ]]);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $response = $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertOk();
        $response->assertSee('Занятие 1')->assertSee('Разбор кейса')->assertSee('Разбор решений команды')
            ->assertSee('Папка группы')->assertSee('Библиотека MBA')->assertSee('https://example.com/folder')->assertSee('https://example.com/books')
            ->assertSee('Мой вопрос')->assertDontSee('id="help"', false)->assertDontSee('href="#help"', false);
        $this->assertSame(1, substr_count($response->getContent(), '19.10.2026'));
        $this->assertSame(1, substr_count($response->getContent(), '26.10.2026'));
    }

    public function test_legacy_program_video_stays_published_after_editing_its_template(): void
    {
        $program = Program::factory()->create(['template_key' => 'short', 'page_blocks' => [
            ['type' => 'recordings', 'data' => ['title' => 'Записи', 'visible' => true, 'videos' => [['title' => 'Старая запись', 'url' => 'https://example.com/legacy']]]],
        ]]);
        $this->actingAs(User::factory()->create(['role' => 'senior']));
        Livewire::test(EditProgram::class, ['record' => $program->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame('published', $program->fresh()->page_blocks[0]['data']['videos'][0]['status']);
    }

    public function test_calendar_archive_keeps_current_month_until_ekb_month_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-11-30 18:59:00', 'UTC'));
        $group = StudyGroup::factory()->create();
        $data = ['collapse_past' => true, 'source_url' => 'https://example.com/private-sheet', 'lessons' => array_map(fn ($date) => ['date' => $date, 'title' => $date, 'time' => '10:00–11:00'], ['2026-09-01', '2026-10-01', '2026-11-01', '2026-12-01'])];
        $render = fn () => view('student.page-block', ['type' => 'schedule', 'data' => $data, 'group' => $group])->render();
        $html = $render();
        $this->assertSame(1, substr_count($html, 'Прошедшие занятия'));
        $end = strpos($html, '</details>');
        $this->assertLessThan($end, strpos($html, '01.10.2026'));
        $this->assertGreaterThan($end, strpos($html, '01.11.2026'));
        $this->assertStringNotContainsString('private-sheet', $html);
        $this->travelTo(Carbon::parse('2026-11-30 19:00:00', 'UTC'));
        $html = $render();
        $this->assertLessThan(strpos($html, '</details>'), strpos($html, '01.11.2026'));
    }

    public function test_preparation_moves_first_and_disappears_after_one_calendar_month_without_deleting(): void
    {
        $group = StudyGroup::factory()->create(['starts_at' => '2026-01-31', 'page_blocks' => [
            ['type' => 'info', 'data' => ['title' => 'Другое', 'block_id' => 'other']],
            ['type' => 'faq', 'data' => ['title' => 'Как подготовиться к обучению', 'block_id' => 'prepare', 'questions' => [['question' => 'Чат', 'answer' => 'Присоединитесь']]]],
        ]]);
        $this->travelTo(Carbon::parse('2026-02-27 23:59', 'Asia/Yekaterinburg'));
        $this->assertSame('preparation', CoursePage::visibleBlocks($group)[0]['type']);
        $this->travelTo(Carbon::parse('2026-02-28 00:00', 'Asia/Yekaterinburg'));
        $this->assertCount(1, CoursePage::visibleBlocks($group));
        $this->assertCount(2, CoursePage::forEditor(CoursePage::forGroup($group->fresh())));
    }

    public function test_recordings_open_latest_four_preserve_other_links_and_remove_repeated_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00', 'Asia/Yekaterinburg'));
        $group = StudyGroup::factory()->create();
        $videos = array_map(fn ($n) => ['date' => '2026-11-0'.$n, 'title' => 'Занятие '.$n.' 0'.$n.'.11.2026', 'text' => 'Описание 0'.$n.'.11.2026', 'module' => 'Записи занятий', 'url' => 'https://example.com/video-'.$n], range(1, 6));
        $html = view('student.page-block', ['type' => 'recordings', 'data' => ['videos' => $videos], 'group' => $group])->render();
        $this->assertSame(4, substr_count(explode('<details', $html)[0], 'class="recording-row'));
        $this->assertSame(1, substr_count($html, 'Предыдущие записи'));
        $this->assertStringNotContainsString('<h3>Записи занятий</h3>', $html);
        foreach (range(1, 6) as $n) {
            $this->assertSame(1, substr_count($html, '0'.$n.'.11.2026'));
            $this->assertStringContainsString('https://example.com/video-'.$n, $html);
        }
        $this->assertLessThan(strpos($html, 'Занятие 5'), strpos($html, 'Занятие 6'));
    }

    public function test_public_header_shows_start_only_and_completion_does_not_hide_future_lessons(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00', 'Asia/Yekaterinburg'));
        $group = StudyGroup::factory()->create(['starts_at' => '2026-04-03', 'graduates_at' => '2026-11-08', 'page_blocks' => []]);
        $this->actingAs($group->administrator)->get(route('student.group', $group))->assertOk()->assertSee('Начало обучения')->assertSee('03.04.2026')->assertDontSee('08.11.2026')->assertSee('Обучение завершено');
        $group->update(['page_blocks' => [['type' => 'schedule', 'data' => ['title' => 'Расписание', 'lessons' => [['date' => '2026-11-13', 'title' => 'Дополнительная встреча']]]]]]);
        $this->get(route('student.group', $group))->assertOk()->assertSee('Дополнительная встреча')->assertDontSee('Обучение завершено');
    }

    public function test_unsaved_block_preview_uses_draft_escapes_content_and_never_persists(): void
    {
        $group = StudyGroup::factory()->create(['page_blocks' => [['type' => 'info', 'data' => ['title' => 'Сохранённый', 'text' => 'База', 'block_id' => 'own']]]]);
        $this->actingAs($group->administrator);
        $original = $group->page_blocks;
        $draft = ['type' => 'info', 'data' => ['title' => 'Черновик', 'text' => '<script>опасно</script>', 'url' => 'javascript:alert(1)', 'block_id' => 'own']];
        $html = CoursePage::previewBlock($group, $draft);
        $this->assertStringContainsString('Черновик', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame($original, $group->fresh()->page_blocks);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->expectException(HttpException::class);
        CoursePage::previewBlock($group, $draft);
    }

    public function test_hero_preview_reflects_draft_upload_position_and_clear_without_saving(): void
    {
        Storage::fake('public');
        $group = StudyGroup::factory()->create(['description' => 'Сохранённое приветствие', 'hero_image' => 'group-covers/saved.jpg', 'hero_position' => 'center']);
        $this->actingAs($group->administrator);
        $form = Livewire::test(EditGroupPage::class, ['record' => $group->id]);
        $form->fillForm(['description' => 'Черновик <script>опасно</script>', 'hero_position' => 'top', 'hero_image' => UploadedFile::fake()->image('draft.jpg', 2400, 900)]);
        $html = GroupHero::preview($group, $form->get('data'));
        $this->assertStringContainsString('/preview-file/', $html);
        $this->assertStringContainsString('object-position:top', $html);
        $this->assertStringContainsString('Черновик &lt;script&gt;', $html);
        $this->assertStringNotContainsString('/storage/group-covers/saved.jpg', $html);
        $form->assertSee('Предпросмотр главного экрана');
        $cleared = GroupHero::preview($group, ['hero_image' => [], 'hero_position' => 'invalid', 'description' => '']);
        $this->assertStringContainsString('object-position:center', $cleared);
        $this->assertStringNotContainsString('Сохранённое приветствие', $cleared);
        $this->assertStringNotContainsString('/storage/group-covers/saved.jpg', $cleared);
        $this->assertSame('Сохранённое приветствие', $group->fresh()->description);
        $this->assertSame('group-covers/saved.jpg', $group->fresh()->hero_image);
        $this->assertSame('center', $group->fresh()->hero_position);
        Storage::disk('public')->assertDirectoryEmpty('group-covers');
    }

    public function test_hero_preview_requires_permission_to_edit_group(): void
    {
        $group = StudyGroup::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->expectException(HttpException::class);
        GroupHero::preview($group, ['description' => 'Чужая группа']);
    }

    public function test_pencil_targets_alias_in_current_group_and_is_hidden_from_students(): void
    {
        $source = StudyGroup::factory()->create(['page_blocks' => [['type' => 'info', 'data' => ['title' => 'Источник', 'text' => 'Общий текст', 'block_id' => 'source']]]]);
        $group = StudyGroup::factory()->create(['page_blocks' => [['type' => 'alias', 'data' => ['title' => 'Общий блок', 'source_group' => $source->id, 'source_block' => 'source', 'block_id' => 'alias-here']]]]);
        $this->actingAs($group->administrator)->get(route('student.group', $group))->assertOk()->assertSee('block=alias-here')->assertSee('id="course-block-alias-here"', false);
        $this->get(StudyGroupResource::getUrl('page', ['record' => $group, 'block' => 'alias-here']))
            ->assertOk()->assertSee('data-course-block="alias-here"', false);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $this->actingAs($enrollment->user)->get(route('student.group', $group))->assertOk()->assertDontSee('block-edit-link');
    }
}
