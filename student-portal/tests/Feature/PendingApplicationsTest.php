<?php

namespace Tests\Feature;

use App\Filament\Pages\Students;
use App\Filament\Resources\EnrollmentApplications\Pages\ListEnrollmentApplications;
use App\Filament\Resources\StudyGroups\Pages\ListStudyGroups;
use App\Models\Enrollment;
use App\Models\EnrollmentApplication;
use App\Models\StudyGroup;
use App\Models\User;
use App\Services\Admissions;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PendingApplicationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_directory_shows_pending_groups_and_links_to_the_correct_applications(): void
    {
        $group = StudyGroup::factory()->create();
        $request = EnrollmentApplication::factory()->create(['study_group_id' => $group->id, 'status' => 'pending']);
        $unrelated = User::factory()->create();
        EnrollmentApplication::factory()->create(['user_id' => $unrelated->id, 'study_group_id' => $group->id, 'status' => 'pending']);
        $this->actingAs($group->administrator);
        $page = Livewire::test(Students::class)
            ->filterTable('group', $group->id)->filterTable('membership', 'pending')->searchTable($request->user->email)
            ->assertCountTableRecords(1)->assertCanSeeTableRecords([$request->user])->assertCanNotSeeTableRecords([$unrelated])
            ->assertSee($group->code)->assertSee('Ожидает одобрения')
            ->assertTableColumnStateSet('access', 'Ожидает одобрения', $request->user)
            ->call('selectStudent', $request->user_id)->assertSee($group->title)->assertSee('Перейти к заявке');
        $url = $page->instance()->applicationListUrl($request->user, $group->id);
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        Livewire::withQueryParams($params)->test(ListEnrollmentApplications::class)
            ->assertSet('tableSearch', $request->user->email)->assertSet('tableFilters.status.value', 'pending')
            ->assertSet('tableFilters.study_group_id.value', (string) $group->id)
            ->assertCountTableRecords(1)->assertCanSeeTableRecords([$request]);
        $this->assertFalse($group->availableTo($request->user));
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_group_counts_and_filters_distinguish_membership_from_access_to_another_group(): void
    {
        $group = StudyGroup::factory()->create();
        $other = StudyGroup::factory()->create(['graduates_at' => now()->addYear()]);
        $student = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'study_group_id' => $other->id]);
        EnrollmentApplication::factory()->create(['user_id' => $student->id, 'study_group_id' => $group->id, 'status' => 'pending']);
        $this->actingAs(User::factory()->create(['role' => 'senior']));
        Livewire::test(Students::class)->assertTableColumnStateSet('access', 'Открыт', $student)
            ->filterTable('learning_kind', 'program')->filterTable('membership', 'pending')->assertCountTableRecords(1);
        Livewire::test(Students::class)->filterTable('group', $other->id)->filterTable('membership', 'pending')->assertCountTableRecords(0);
        Livewire::withQueryParams(['filters' => ['membership' => ['value' => 'pending']]])->test(Students::class)->assertSet('tableFilters.membership.value', 'pending')->assertCountTableRecords(1);
        $groups = Livewire::test(ListStudyGroups::class);
        $record = $groups->instance()->getTableRecords()->firstWhere('id', $group->id);
        $this->assertSame(1, $record->pending_applications_count);
        $groups->assertTableColumnStateSet('enrollments_count', 0, $group)->assertSee('Ожидают одобрения: 1');
        Livewire::withQueryParams(['group' => $group->id])->test(Students::class)
            ->assertCountTableRecords(1)->assertTableColumnStateSet('access', 'Ожидает одобрения', $student)
            ->assertSee('Рассмотреть заявки (1)')->filterTable('has_access', false)->assertCountTableRecords(1)
            ->filterTable('membership', 'enrolled')->assertCountTableRecords(0);
        Livewire::withQueryParams(['group' => $other->id])->test(Students::class)
            ->filterTable('membership', 'pending')->assertCountTableRecords(0);
    }

    public function test_approval_rejection_and_reassignment_refresh_the_group_directory_without_duplicates(): void
    {
        $group = StudyGroup::factory()->create();
        $next = StudyGroup::factory()->create();
        $admin = User::factory()->create(['role' => 'senior']);
        $request = EnrollmentApplication::factory()->create(['study_group_id' => $group->id, 'status' => 'pending']);
        $this->actingAs($admin);
        $page = Livewire::withQueryParams(['group' => $group->id])->test(Students::class)->assertCountTableRecords(1);
        app(Admissions::class)->assign($admin, $request, $next->id);
        $page->call('$refresh')->assertCountTableRecords(0);
        $nextPage = Livewire::withQueryParams(['group' => $next->id])->test(Students::class)->assertCountTableRecords(1);
        app(Admissions::class)->approve($admin, $request->fresh(), false);
        $nextPage->call('$refresh')->assertCountTableRecords(1)->assertTableActionHidden('applications', $request->user)
            ->filterTable('membership', 'pending')->assertCountTableRecords(0)
            ->filterTable('membership', 'enrolled')->assertCountTableRecords(1);
        $rejected = EnrollmentApplication::factory()->create(['study_group_id' => $group->id, 'status' => 'pending']);
        $page->call('$refresh')->assertCountTableRecords(1);
        app(Admissions::class)->reject($admin, $rejected, 'Проверьте группу');
        $page->call('$refresh')->assertCountTableRecords(0);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_unassigned_application_details_follow_existing_view_permissions(): void
    {
        $request = EnrollmentApplication::factory()->create(['study_group_id' => null, 'status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin', 'reviews_unassigned' => false]);
        $this->actingAs($admin);
        Livewire::test(Students::class)->call('selectStudent', $request->user_id)->assertDontSee('Ожидающие заявки')
            ->filterTable('membership', 'pending')->assertCountTableRecords(0);
        $admin->forceFill(['reviews_unassigned' => true])->save();
        Livewire::test(Students::class)->filterTable('membership', 'pending')->assertCountTableRecords(1)
            ->call('selectStudent', $request->user_id)->assertSee('Группа не выбрана')->assertSee('Ожидающие заявки');
    }
}
