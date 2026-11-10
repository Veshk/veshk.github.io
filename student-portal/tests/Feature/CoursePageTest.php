<?php

namespace Tests\Feature;

use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\StudyGroups\Pages\CreateStudyGroup;
use App\Filament\Resources\StudyGroups\Pages\EditGroupPage;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
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
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['page_blocks' => $blocks])->call('save')->assertHasNoFormErrors();
        $this->assertSame($blocks, $group->fresh()->page_blocks);
        $this->assertSame($group->administrator_id, $group->fresh()->administrator_id);
        $this->actingAs(User::factory()->create(['role' => 'senior']))->get('/admin/study-groups/'.$group->id.'/page')->assertOk();
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

    public function test_legacy_program_video_stays_published_after_editing_its_template(): void
    {
        $program = Program::factory()->create(['template_key' => 'short', 'page_blocks' => [
            ['type' => 'recordings', 'data' => ['title' => 'Записи', 'visible' => true, 'videos' => [['title' => 'Старая запись', 'url' => 'https://example.com/legacy']]]],
        ]]);
        $this->actingAs(User::factory()->create(['role' => 'senior']));
        Livewire::test(EditProgram::class, ['record' => $program->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame('published', $program->fresh()->page_blocks[0]['data']['videos'][0]['status']);
    }
}
