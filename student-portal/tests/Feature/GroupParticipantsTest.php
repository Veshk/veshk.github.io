<?php

namespace Tests\Feature;

use App\Filament\Pages\Students;
use App\Filament\Resources\StudyGroups\Pages\ListStudyGroups;
use App\Filament\Resources\StudyGroups\Pages\ViewStudyGroup;
use App\Models\Enrollment;
use App\Models\EnrollmentApplication;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupParticipantsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_group_count_and_link_list_only_enrolled_students_including_expired_access(): void
    {
        $group = StudyGroup::factory()->create(['graduates_at' => now()->subYear(), 'access_days' => 0]);
        $empty = StudyGroup::factory()->create();
        $member = User::factory()->create();
        $pending = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $member->id, 'study_group_id' => $group->id]);
        Enrollment::factory()->create(['user_id' => $group->administrator_id, 'study_group_id' => $group->id]);
        EnrollmentApplication::factory()->create(['user_id' => $pending->id, 'study_group_id' => $group->id, 'status' => 'pending']);
        $this->actingAs($empty->administrator);

        $table = Livewire::test(ListStudyGroups::class)
            ->assertTableColumnVisible('enrollments_count')
            ->assertTableColumnStateSet('enrollments_count', 1, $group)
            ->assertTableColumnStateSet('enrollments_count', 0, $empty);
        $column = $table->instance()->getTable()->getColumn('enrollments_count')->record($group);
        $this->assertSame(Students::getUrl(['group' => $group->id]), $column->getUrl());
        Livewire::test(ViewStudyGroup::class, ['record' => $group->id])->assertSee('1 · Открыть список →');
        Livewire::withQueryParams(['group' => $group->id])->test(Students::class)
            ->assertSee($group->title)->assertSee($group->code)
            ->assertCountTableRecords(1)->assertCanSeeTableRecords([$member])->assertCanNotSeeTableRecords([$pending, $group->administrator])
            ->assertTableColumnStateSet('access', 'Завершён', $member);
        Livewire::withQueryParams(['group' => $empty->id])->test(Students::class)->assertCountTableRecords(0)->assertSee('Участники не найдены');
    }

    public function test_group_access_and_history_keep_the_group_context_and_respect_individual_extensions(): void
    {
        $expired = StudyGroup::factory()->create(['graduates_at' => now()->subYear(), 'access_days' => 0]);
        $active = StudyGroup::factory()->create(['graduates_at' => now()->addYear()]);
        $member = User::factory()->create();
        $extended = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$member, $extended] as $student) {
            Enrollment::factory()->create(['user_id' => $student->id, 'study_group_id' => $expired->id, 'extended_until' => $student->is($extended) ? now()->addMonth() : null]);
        }
        Enrollment::factory()->create(['user_id' => $member->id, 'study_group_id' => $active->id]);
        $visit = $member->portalVisits()->create(['visited_at' => now(), 'page' => 'Моё обучение']);
        $this->actingAs($active->administrator);
        Livewire::withQueryParams(['group' => $expired->id])->test(Students::class)
            ->assertCountTableRecords(2)
            ->assertTableColumnStateSet('access', 'Завершён', $member)
            ->assertTableColumnStateSet('access', 'Открыт', $extended)
            ->searchTable($member->email)->assertCountTableRecords(1)
            ->call('selectStudent', $member->id)->assertCanSeeTableRecords([$visit])->assertSee('К участникам группы')
            ->call('selectStudent', null)->assertSet('selectedGroupId', $expired->id)->assertCountTableRecords(1)
            ->searchTable('')->assertCountTableRecords(2);
        Livewire::withQueryParams(['group' => $expired->id, 'student' => $other->id])->test(Students::class)->assertNotFound();
    }

    public function test_participants_are_available_to_staff_only_and_unknown_groups_do_not_show_everyone(): void
    {
        $group = StudyGroup::factory()->create();
        $url = Students::getUrl(['group' => $group->id]);
        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        foreach (['admin', 'senior'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertOk();
        }
        Livewire::withQueryParams(['group' => 999999])->test(Students::class)->assertNotFound();
    }
}
