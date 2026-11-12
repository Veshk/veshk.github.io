<?php

namespace Tests\Feature;

use App\Filament\Pages\Students;
use App\Filament\Resources\Programs\Pages\EditProgramTemplate;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Filament\Resources\StudyGroups\Pages\ListStudyGroups;
use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_group_settings_modal_saves_only_metadata_and_respects_ownership(): void
    {
        $group = StudyGroup::factory()->create();
        $blocks = $group->page_blocks;
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($group->administrator);
        Livewire::test(ListStudyGroups::class)
            ->callAction(TestAction::make('edit')->table($group), data: ['number' => '321', 'admin_notes' => 'Обновлено в окне', 'administrator_id' => $other->id])
            ->assertHasNoActionErrors();
        $this->assertSame('Обновлено в окне', $group->fresh()->admin_notes);
        $this->assertSame($group->administrator_id, $group->fresh()->administrator_id);
        $this->assertSame($blocks, $group->fresh()->page_blocks);
        $this->actingAs($other);
        Livewire::test(ListStudyGroups::class)->assertTableActionHidden('edit', $group)->assertTableActionVisible('view', $group);
    }

    public function test_program_settings_and_template_are_independent_and_protected(): void
    {
        $program = Program::factory()->create(['page_blocks' => CoursePage::defaults('short'), 'template_key' => 'short']);
        $group = StudyGroup::factory()->for($program)->create();
        $blocks = $program->page_blocks;
        $this->actingAs(User::factory()->create(['role' => 'senior']));
        Livewire::test(ListPrograms::class)->assertCanSeeTableRecords([$program])
            ->callAction(TestAction::make('edit')->table($program), data: ['title' => 'Новое название'])
            ->assertHasNoActionErrors();
        $this->assertSame('Новое название', $program->fresh()->title);
        $this->assertSame($blocks, $program->fresh()->page_blocks);
        Livewire::test(EditProgramTemplate::class, ['record' => $program->id])->fillForm(['page_blocks' => []])->call('save')->assertHasNoFormErrors();
        $this->assertSame([], $program->fresh()->page_blocks);
        $this->assertSame('Новое название', $program->fresh()->title);
        $this->assertSame($blocks, $group->fresh()->page_blocks);
        $this->actingAs($group->administrator);
        Livewire::test(ListPrograms::class)->assertTableActionHidden('edit', $program)->assertTableActionHidden('template', $program);
        $this->get('/admin/programs/'.$program->id.'/template')->assertForbidden();
    }

    public function test_student_history_paginates_without_changing_directory_filters(): void
    {
        $student = User::factory()->create();
        for ($i = 1; $i <= 26; $i++) {
            $student->portalVisits()->create(['visited_at' => now()->addSeconds($i), 'page' => 'Страница визита '.$i]);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(Students::class)->searchTable($student->email)
            ->call('selectStudent', $student->id)->assertSee('Страница визита 26')->assertDontSee('>Страница визита 1<', false)
            ->call('changeHistoryPage', 2)->assertSee('Страница визита 1')->assertDontSee('Страница визита 26')
            ->assertCountTableRecords(1)->call('selectStudent', null)->assertCountTableRecords(1);
    }

    public function test_create_dialogs_save_a_program_and_group_with_their_defaults(): void
    {
        $senior = User::factory()->create(['role' => 'senior']);
        $this->actingAs($senior);
        Livewire::test(ListPrograms::class)->callAction('create', data: ['title' => 'Программа из окна', 'tag' => 'ОКНО'])->assertHasNoActionErrors();
        $program = Program::where('tag', 'ОКНО')->sole();
        Livewire::test(ListStudyGroups::class)->callAction('create', data: [
            'program_id' => $program->id, 'title' => 'Группа из окна', 'number' => '1', 'administrator_id' => $senior->id,
            'status' => 'Запускается', 'format' => 'Очно', 'starts_at' => '2026-11-11', 'graduates_at' => '2027-01-11', 'access_days' => 30,
        ])->assertHasNoActionErrors();
        $group = StudyGroup::where('code', 'ОКНО-1')->sole();
        $this->assertNotEmpty($group->page_blocks);
        $table = Livewire::test(ListStudyGroups::class)->instance()->getTable();
        $this->assertNull($table->getAction('edit')->record($group)->getUrl());
        $this->assertNull($table->getAction('view')->record($group)->getUrl());
    }

    public function test_group_card_has_portal_link_and_edit_link_only_for_authorized_admin(): void
    {
        $group = StudyGroup::factory()->create();
        $this->actingAs($group->administrator);
        $component = Livewire::test(ListStudyGroups::class)
            ->mountAction(TestAction::make('view')->table($group))
            ->assertActionMounted(TestAction::make('view')->table($group));
        $actions = $component->instance()->getMountedAction()->getVisibleModalFooterActions();
        $this->assertSame(route('student.group', $group), $actions['openPortal']->getUrl());
        $this->assertSame(StudyGroupResource::getUrl('page', ['record' => $group]), $actions['editPortal']->getUrl());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $component = Livewire::test(ListStudyGroups::class)
            ->mountAction(TestAction::make('view')->table($group))
            ->assertActionMounted(TestAction::make('view')->table($group));
        $actions = $component->instance()->getMountedAction()->getVisibleModalFooterActions();
        $this->assertSame(route('student.group', $group), $actions['openPortal']->getUrl());
        $this->assertArrayNotHasKey('editPortal', $actions);
    }
}
