<?php

namespace Tests\Feature;

use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\StudyGroups\Pages\CreateStudyGroup;
use App\Filament\Resources\StudyGroups\Pages\EditStudyGroup;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProgramCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_senior_creates_program_with_exact_manual_tag_and_rejects_duplicate_or_missing_tag(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'senior']));

        Livewire::test(CreateProgram::class)->fillForm(['title' => 'Стратегия', 'tag' => '', 'section' => 'Менеджмент'])->call('create')->assertHasFormErrors(['tag' => 'required']);
        $this->assertDatabaseCount('programs', 0);
        Livewire::test(CreateProgram::class)->fillForm(['title' => 'Стратегия', 'tag' => ' ОНЛ_Стратегия/01 ', 'section' => 'Менеджмент'])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('programs', ['title' => 'Стратегия', 'tag' => 'ОНЛ_Стратегия/01', 'section' => 'Менеджмент']);
        Livewire::test(CreateProgram::class)->fillForm(['title' => 'Другая программа', 'tag' => '  ОНЛ_Стратегия/01 '])->call('create')->assertHasFormErrors(['tag' => 'unique']);
        $this->assertDatabaseCount('programs', 1);
    }

    #[TestWith(['student', false, false])]
    #[TestWith(['admin', true, false])]
    #[TestWith(['senior', true, true])]
    public function test_program_permissions_follow_staff_role(string $role, bool $view, bool $edit): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create(['role' => $role]);

        $this->assertSame($view, $user->can('viewAny', Program::class));
        $this->assertSame($view, $user->can('view', $program));
        $this->assertSame($edit, $user->can('create', Program::class));
        $this->assertSame($edit, $user->can('update', $program));
        $this->assertFalse($user->can('delete', $program));
    }

    public function test_administrator_can_read_program_but_cannot_open_edit_or_create_pages(): void
    {
        $program = Program::factory()->create(['tag' => 'Каталог_01']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/programs/'.$program->id)->assertOk()->assertSee('Каталог_01');
        $this->get('/admin/programs/create')->assertForbidden();
        $this->get('/admin/programs/'.$program->id.'/edit')->assertForbidden();
    }

    public function test_group_form_saves_program_and_separate_airtable_fields_without_sharing_content(): void
    {
        $program = Program::factory()->create(['title' => 'Общая программа']);
        $senior = User::factory()->create(['role' => 'senior']);
        $other = StudyGroup::factory()->for($program)->create(['schedule' => [['title' => 'Чужое занятие']], 'materials' => [['title' => 'Чужой файл']]]);
        $this->actingAs($senior);

        Livewire::test(CreateStudyGroup::class)->fillForm([
            'program_id' => $program->id, 'title' => 'Название для группы 2', 'code' => 'Корп/001-2', 'number' => '001-2',
            'group_type' => 'Корп. обучение', 'status' => 'Запускается', 'administrator_id' => $senior->id,
            'format' => 'Онлайн', 'starts_at' => '2026-11-10', 'graduates_at' => '2026-12-10', 'access_days' => 30,
            'extended_until' => '2027-02-01', 'chat_url' => 'https://example.com/chat', 'registration_open' => true,
            'admin_notes' => 'Служебное условие', 'setup_completed' => true, 'description' => 'Приветствие группы',
        ])->call('create')->assertHasNoFormErrors();

        $group = StudyGroup::where('code', 'Корп/001-2')->sole();
        $this->assertDatabaseHas('study_groups', ['id' => $group->id, 'program_id' => $program->id, 'number' => '001-2', 'group_type' => 'Корп. обучение', 'status' => 'Запускается', 'chat_url' => 'https://example.com/chat', 'setup_completed' => true, 'admin_notes' => 'Служебное условие']);
        $this->assertNull($group->schedule);
        $this->assertNull($group->materials);
        $this->assertSame('2027-02-01', $group->closesAt()->toDateString());
        $this->assertSame([['title' => 'Чужой файл']], $other->fresh()->materials);
        Livewire::test(EditProgram::class, ['record' => $program->id])->fillForm(['title' => 'Обновлённая программа', 'section' => 'HR'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Название для группы 2', $group->fresh()->title);
        $this->assertSame('HR', $group->fresh()->program->section);
    }

    public function test_group_form_rejects_missing_or_unknown_program_and_unsafe_chat_url(): void
    {
        $senior = User::factory()->create(['role' => 'senior']);
        $program = Program::factory()->create();
        $this->actingAs($senior);
        $data = ['title' => 'Новая группа', 'code' => 'ТЕСТ/1', 'administrator_id' => $senior->id, 'format' => 'Очно', 'status' => 'Запускается', 'starts_at' => '2026-11-10', 'graduates_at' => '2026-12-10', 'access_days' => 30];

        Livewire::test(CreateStudyGroup::class)->fillForm($data)->call('create')->assertHasFormErrors(['program_id' => 'required']);
        Livewire::test(CreateStudyGroup::class)->fillForm($data + ['program_id' => 999999])->call('create')->assertHasFormErrors(['program_id']);
        Livewire::test(CreateStudyGroup::class)->fillForm($data + ['program_id' => $program->id, 'chat_url' => 'javascript:alert(1)'])->call('create')->assertHasFormErrors(['chat_url']);
        $this->assertDatabaseCount('study_groups', 0);
    }

    public function test_owner_can_edit_group_details_but_cannot_reassign_it_and_notes_stay_private(): void
    {
        $group = StudyGroup::factory()->create(['code' => 'Группа_01', 'materials' => [['title' => 'Материал']]]);
        $other = User::factory()->create(['role' => 'admin']);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $this->actingAs($group->administrator);

        Livewire::test(EditStudyGroup::class, ['record' => $group->id])->fillForm([
            'number' => '004', 'status' => 'Идет обучение', 'chat_url' => 'https://example.com/private-chat',
            'admin_notes' => 'Внутренний комментарий только сотрудникам', 'administrator_id' => $other->id,
        ])->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseHas('study_groups', ['id' => $group->id, 'administrator_id' => $group->administrator_id, 'number' => '004', 'status' => 'Идет обучение']);
        $this->actingAs($other)->get('/admin/study-groups/'.$group->id)->assertOk()->assertSee('Только просмотр');
        $this->get('/admin/study-groups/'.$group->id.'/edit')->assertForbidden();
        $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertOk()->assertSee('https://example.com/private-chat')->assertDontSee('Внутренний комментарий только сотрудникам');
    }

    public function test_group_extension_preserves_personal_extension_and_closes_at_end_of_day(): void
    {
        $this->travelTo(now()->setDate(2027, 2, 1)->setTime(23, 59, 59));
        $group = StudyGroup::factory()->create(['graduates_at' => '2026-12-10', 'access_days' => 30, 'extended_until' => '2027-02-01']);
        $normal = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $extended = Enrollment::factory()->create(['study_group_id' => $group->id, 'extended_until' => '2027-03-01']);

        $this->assertTrue($group->availableTo($normal->user));
        $this->travel(1)->seconds();
        $this->assertFalse($group->availableTo($normal->user));
        $this->assertTrue($group->availableTo($extended->user));
        $group->update(['extended_until' => '2026-12-11']);
        $this->assertSame('2027-01-09', $group->closesAt()->toDateString());
    }
}
