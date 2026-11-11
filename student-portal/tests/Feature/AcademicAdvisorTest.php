<?php

namespace Tests\Feature;

use App\Filament\Resources\AcademicAdvisors\Pages\ManageAcademicAdvisors;
use App\Filament\Resources\StudyGroups\Pages\EditGroupPage;
use App\Models\AcademicAdvisor;
use App\Models\Enrollment;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicAdvisorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_nearest_day_keeps_all_sessions_until_last_one_ends_in_ekaterinburg(): void
    {
        $blocks = [['type' => 'schedule', 'data' => ['lessons' => [
            ['date' => '2026-11-11', 'time' => '14:00–17:15', 'title' => 'День'],
            ['date' => '2026-11-12', 'time' => '10:00–13:00', 'title' => 'Завтра'],
            ['date' => '2026-11-11', 'time' => '10:00–13:00', 'title' => 'Утро'],
            ['date' => '2026-02-30', 'title' => 'Ошибка'],
        ]]], ['type' => 'schedule', 'data' => ['visible' => false, 'lessons' => [['date' => '2026-11-10', 'title' => 'Скрыто']]]]];
        foreach (['2026-11-10T20:00:00Z', '2026-11-11T08:30:00Z', '2026-11-11T12:14:59Z'] as $time) {
            $this->assertSame(['Утро', 'День'], array_column(CoursePage::upcoming($blocks, Carbon::parse($time)), 'title'));
        }
        $this->assertSame(['Завтра'], array_column(CoursePage::upcoming($blocks, Carbon::parse('2026-11-11T12:15:00Z')), 'title'));
        $this->assertSame([], CoursePage::upcoming($blocks, Carbon::parse('2026-11-13T00:00:00Z')));
    }

    public function test_senior_manages_profiles_with_email_and_photo_validation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'senior']));
        Livewire::test(ManageAcademicAdvisors::class)->callAction('create', data: ['name' => 'Научный руководитель', 'email' => 'test+kd@example.com', 'photo_url' => 'https://example.com/photo.png'])->assertHasNoActionErrors();
        $advisor = AcademicAdvisor::sole();
        $this->assertSame('mailto:test%2Bkd@example.com', $advisor->emailUrl());
        Livewire::test(ManageAcademicAdvisors::class)->callTableAction('edit', $advisor, ['name' => 'Новое имя', 'email' => 'new@example.com', 'photo_url' => 'https://example.com/new.png'])->assertHasNoActionErrors();
        $this->assertSame('Новое имя', $advisor->fresh()->name);
        Livewire::test(ManageAcademicAdvisors::class)->callAction('create', data: ['name' => 'Ошибка', 'email' => 'bad', 'photo_url' => 'javascript:alert(1)'])->assertHasActionErrors(['email', 'photo_url']);
        $this->assertDatabaseCount('academic_advisors', 1);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/academic-advisors')->assertOk();
        $this->assertFalse(auth()->user()->can('create', AcademicAdvisor::class));
        $this->assertFalse(auth()->user()->can('update', $advisor));
        Livewire::test(ManageAcademicAdvisors::class)->assertActionHidden('create')->assertTableActionHidden('edit', $advisor);
        $this->actingAs(User::factory()->create())->get('/admin/academic-advisors')->assertForbidden();
    }

    public function test_group_editor_assigns_shared_adviser_and_student_sees_current_profile(): void
    {
        $advisor = AcademicAdvisor::factory()->create(['email' => 'first@example.com', 'photo_url' => 'https://example.com/photo.png']);
        $group = StudyGroup::factory()->create();
        $other = StudyGroup::factory()->create(['academic_advisor_id' => $advisor->id]);
        $this->actingAs($group->administrator);
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['academic_advisor_id' => $advisor->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($advisor->id, $group->fresh()->academic_advisor_id);
        $this->get('/admin/study-groups/'.$other->id.'/page')->assertForbidden();
        $advisor->update(['email' => 'updated@example.com']);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertOk()->assertSeeInOrder(['learning-overview', 'Научный руководитель', 'mailto:updated@example.com', 'course-nav'], false)->assertDontSee('mailto:first@example.com', false);
        $this->assertSame('updated@example.com', $other->fresh()->academicAdvisor->email);
        $this->actingAs($group->administrator);
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['academic_advisor_id' => null])->call('save')->assertHasNoFormErrors();
        $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertDontSee('Научный руководитель');
    }

    public function test_kd_seed_preserves_source_dates_links_and_later_edits(): void
    {
        $this->seed();
        $group = StudyGroup::where('code', 'КД-26')->sole();
        $this->assertSame('2026-04-03', $group->starts_at->toDateString());
        $this->assertSame('2026-12-09', $group->closesAt()->toDateString());
        $this->assertSame('rublevskaya.julia@veshk.ru', $group->academicAdvisor->email);
        $this->assertCount(32, collect($group->page_blocks)->firstWhere('type', 'schedule')['data']['lessons']);
        $this->assertCount(3, collect($group->page_blocks)->firstWhere('type', 'recordings')['data']['videos']);
        $group->update(['description' => 'Своя правка']);
        $group->academicAdvisor->update(['email' => 'edited@example.com']);
        $this->seed();
        $this->assertSame('Своя правка', $group->fresh()->description);
        $this->assertSame('edited@example.com', $group->fresh()->academicAdvisor->email);
        $this->assertSame(1, StudyGroup::where('code', 'КД-26')->count());
    }
}
