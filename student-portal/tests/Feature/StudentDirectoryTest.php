<?php

namespace Tests\Feature;

use App\Filament\Pages\Students;
use App\Models\Enrollment;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentDirectoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_visits_are_recorded_with_existing_authorization_and_only_for_accessible_student_pages(): void
    {
        $student = User::factory()->create(['password' => 'TestPass2026!']);
        $group = StudyGroup::factory()->create(['graduates_at' => now()->addMonth()]);
        $this->post('/login', ['email' => $student->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('portal_visits', 0);
        $this->post('/login', ['email' => $student->email, 'password' => 'TestPass2026!'])->assertRedirect('/learning');
        $this->get('/learning')->assertOk();
        $this->travel(2)->hours();
        $this->get('/learning')->assertOk();
        $this->assertDatabaseCount('portal_visits', 2);
        $this->assertSame(2, $student->portalVisits()->count());
        $this->assertTrue($student->portalVisits()->latest('visited_at')->first()->visited_at->equalTo(now()->startOfSecond()));
        $this->get('/learning/'.$group->id)->assertForbidden();
        $this->assertDatabaseCount('portal_visits', 2);
        Enrollment::factory()->create(['user_id' => $student->id, 'study_group_id' => $group->id]);
        $this->get('/learning/'.$group->id)->assertOk();
        $this->assertDatabaseHas('portal_visits', ['user_id' => $student->id, 'page' => 'Группа: '.$group->title]);
        $this->assertDatabaseCount('portal_visits', 3);
        $this->actingAs($group->administrator)->get('/learning/'.$group->id)->assertOk();
        $this->assertDatabaseCount('portal_visits', 3);
    }

    public function test_staff_directory_lists_accounts_once_and_filters_group_and_visits(): void
    {
        $first = StudyGroup::factory()->create();
        $second = StudyGroup::factory()->create();
        $student = User::factory()->create();
        $without = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'study_group_id' => $first->id]);
        Enrollment::factory()->create(['user_id' => $student->id, 'study_group_id' => $second->id]);
        $student->portalVisits()->create(['visited_at' => '2026-11-05 08:30:00', 'page' => 'Моё обучение']);
        $this->actingAs($first->administrator);
        Livewire::test(Students::class)->assertCanSeeTableRecords([$student, $without])->assertCountTableRecords(2)
            ->filterTable('group', $first->id)->assertCanSeeTableRecords([$student])->assertCanNotSeeTableRecords([$without]);
        Livewire::test(Students::class)->filterTable('has_visits', false)->assertCanSeeTableRecords([$without])->assertCanNotSeeTableRecords([$student]);
        Livewire::test(Students::class)->searchTable($student->email)->assertCountTableRecords(1);
    }

    public function test_history_is_private_and_separate_for_each_student(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $event = $student->portalVisits()->create(['visited_at' => '2026-11-05 08:30:00', 'page' => 'Моё обучение']);
        $otherEvent = $other->portalVisits()->create(['visited_at' => '2026-11-04 09:00:00', 'page' => 'Моё обучение']);
        $this->get('/admin/students')->assertRedirect('/admin/login');
        $this->actingAs($student)->get('/admin/students?student='.$other->id)->assertForbidden();
        $staff = User::factory()->create(['role' => 'admin']);
        $this->actingAs($staff);
        Livewire::test(Students::class)->call('selectStudent', $student->id)->assertSee($student->email)
            ->assertCanSeeTableRecords([$event])->assertCanNotSeeTableRecords([$otherEvent])->assertSee('05.11.2026 08:30:00');
        Livewire::test(Students::class)->call('selectStudent', $staff->id)->assertNotFound();
    }

    public function test_sample_directory_has_six_active_administrators_with_two_groups_each(): void
    {
        $this->seed();
        $staff = User::whereIn('role', ['admin', 'senior'])->withCount('studyGroups')->get();
        $this->assertCount(6, $staff);
        $this->assertTrue($staff->every(fn ($person) => $person->study_groups_count === 2));
        $this->assertDatabaseCount('study_groups', 12);
        $this->assertDatabaseCount('portal_visits', 0);
        $this->seed();
        $this->assertDatabaseCount('study_groups', 12);
    }
}
