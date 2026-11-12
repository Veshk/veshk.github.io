<?php

namespace Tests\Feature;

use App\Filament\Pages\CourseCatalog;
use App\Filament\Pages\Students;
use App\Models\CourseOrder;
use App\Models\Enrollment;
use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\SelfStudyLesson;
use App\Models\User;
use App\Services\SelfStudyAccess;
use Database\Seeders\SelfStudyCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SelfStudyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_one_account_has_program_free_course_and_recorded_course(): void
    {
        $user = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $user->id]);
        SelfStudyEnrollment::factory()->for($user)->create();
        SelfStudyEnrollment::factory()->for($user)->create(['self_study_course_id' => SelfStudyCourse::factory()->create(['kind' => 'recorded'])->id]);
        $this->actingAs($user)->get('/learning')->assertOk()->assertSee('Программа обучения')->assertSee('Бесплатный курс')->assertSee('Курс в записи');
    }

    public function test_free_access_is_calendar_month_and_repeat_does_not_reset_progress(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(10, 0));
        $course = SelfStudyCourse::factory()->create();
        $user = User::factory()->create();
        $access = app(SelfStudyAccess::class)->grantFree($course, $user, $course->administrator);
        $access->update(['completed_lessons' => [12], 'notes' => ['12' => 'Моя заметка']]);
        $this->assertEquals('2026-02-28 10:00', $access->fresh()->expires_at->format('Y-m-d H:i'));
        $again = app(SelfStudyAccess::class)->grantFree($course, $user, $course->administrator);
        $this->assertSame($access->id, $again->id);
        $this->assertSame([12], $again->completed_lessons);
        $this->assertSame('Моя заметка', $again->notes[12]);
    }

    public function test_unpaid_order_does_not_open_course_and_payment_is_idempotent(): void
    {
        $course = SelfStudyCourse::factory()->create(['kind' => 'recorded']);
        SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id]);
        $user = User::factory()->create();
        $service = app(SelfStudyAccess::class);
        $order = $service->createOrder($course, $user, $course->administrator);
        $this->actingAs($user)->get(route('student.course', $course))->assertForbidden()->assertDontSee('testVideo123');
        $this->assertSame($order->id, $service->createOrder($course, $user, $course->administrator)->id);
        $course->update(['price_minor' => 400000, 'access_days' => 100]);
        $paidAt = now()->subHour()->timezone('Asia/Yekaterinburg')->format('Y-m-d H:i:s');
        $access = $service->confirmPayment($order, $course->administrator, 'bank-001', 300000, $paidAt);
        $this->assertSame(90, (int) $access->starts_at->diffInDays($access->expires_at));
        $this->assertEquals(now()->subHour()->format('Y-m-d H:i'), $access->starts_at->format('Y-m-d H:i'));
        $this->actingAs($user)->get(route('student.course', $course))->assertOk()->assertSee('testVideo123');
        $access->update(['completed_lessons' => [1]]);
        $again = $service->confirmPayment($order, $course->administrator, 'bank-001', 300000, $paidAt);
        $this->assertSame($access->id, $again->id);
        $this->assertSame([1], $again->completed_lessons);
    }

    public function test_wrong_amount_cannot_grant_access(): void
    {
        $order = CourseOrder::factory()->create();
        try {
            app(SelfStudyAccess::class)->confirmPayment($order, $order->course->administrator, 'wrong-amount', 1, now()->subHour()->toIso8601String());
            $this->fail('Wrong amount accepted');
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('self_study_enrollments', 0);
            $this->assertSame('pending', $order->fresh()->status);
        }
    }

    public function test_one_payment_reference_cannot_open_two_orders(): void
    {
        $first = CourseOrder::factory()->create();
        $second = CourseOrder::factory()->create();
        $service = app(SelfStudyAccess::class);
        $service->confirmPayment($first, $first->course->administrator, 'bank-same', 300000, now()->subHour()->toIso8601String());
        try {
            $service->confirmPayment($second, $second->course->administrator, 'bank-same', 300000, now()->subHour()->toIso8601String());
            $this->fail('Duplicate payment accepted');
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('self_study_enrollments', 1);
            $this->assertSame('pending', $second->fresh()->status);
        }
    }

    public function test_progress_and_notes_are_private_and_survive_extension(): void
    {
        $access = SelfStudyEnrollment::factory()->create();
        $lesson = SelfStudyLesson::factory()->create(['self_study_course_id' => $access->self_study_course_id]);
        $other = SelfStudyLesson::factory()->create();
        $this->actingAs($access->user)->post(route('student.lesson.note', [$access->course, $lesson]), ['note' => 'Моя заметка'])->assertRedirect();
        $this->postJson(route('student.lesson.progress', [$access->course, $lesson]), ['completed' => true])->assertOk()->assertJson(['progress' => 100]);
        $this->postJson(route('student.lesson.progress', [$access->course, $lesson]), ['completed' => true])->assertOk();
        $this->assertSame([$lesson->id], $access->fresh()->completed_lessons);
        $this->postJson(route('student.lesson.progress', [$access->course, $other]), ['completed' => true])->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('student.lesson', [$access->course, $lesson]))->assertForbidden();
        $this->postJson(route('student.lesson.progress', [$access->course, $lesson]), ['completed' => true])->assertForbidden();
        app(SelfStudyAccess::class)->extend($access, $access->course->administrator, now()->addMonths(2)->toDateString(), 'По просьбе слушателя');
        $this->assertSame('Моя заметка', $access->fresh()->notes[$lesson->id]);
        $this->assertSame([$lesson->id], $access->fresh()->completed_lessons);
    }

    public function test_expired_revoked_and_unpublished_content_are_closed(): void
    {
        $access = SelfStudyEnrollment::factory()->create(['expires_at' => now()->subMinute()]);
        $lesson = SelfStudyLesson::factory()->create(['self_study_course_id' => $access->self_study_course_id]);
        $this->actingAs($access->user)->get(route('student.course', $access->course))->assertForbidden()->assertDontSee('testVideo123');
        $this->postJson(route('student.lesson.progress', [$access->course, $lesson]), ['completed' => true])->assertForbidden();
        $access->update(['expires_at' => now()->addMonth()]);
        $lesson->update(['is_published' => false]);
        $this->get(route('student.lesson', [$access->course, $lesson]))->assertNotFound();
        app(SelfStudyAccess::class)->revoke($access, $access->course->administrator, 'Доступ закрыт по запросу');
        $this->get(route('student.course', $access->course))->assertForbidden();
    }

    public function test_material_download_requires_active_access_and_matching_lesson(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('course-materials/example.pdf', 'private material');
        $access = SelfStudyEnrollment::factory()->create();
        $lesson = SelfStudyLesson::factory()->create(['self_study_course_id' => $access->self_study_course_id, 'materials' => [['title' => 'Материал', 'path' => 'course-materials/example.pdf']]]);
        $url = route('student.lesson.material', [$access->course, $lesson, 0]);
        $this->actingAs($access->user)->get($url)->assertOk()->assertDownload('example.pdf');
        $access->update(['expires_at' => now()->subSecond()]);
        $this->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_admin_sees_only_own_courses_and_student_cannot_enter_management(): void
    {
        $own = SelfStudyCourse::factory()->create();
        $other = SelfStudyCourse::factory()->create();
        $this->actingAs($own->administrator);
        Livewire::test(CourseCatalog::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
        $this->get('/admin/course-orders')->assertOk();
        $this->get('/admin/course-accesses?course='.$other->id)->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/admin/course-catalog')->assertForbidden();
    }

    public function test_common_directory_shows_one_person_with_all_courses(): void
    {
        $access = SelfStudyEnrollment::factory()->create();
        SelfStudyEnrollment::factory()->create(['user_id' => $access->user_id]);
        Enrollment::factory()->create(['user_id' => $access->user_id]);
        $this->actingAs($access->course->administrator);
        Livewire::test(Students::class)->assertCanSeeTableRecords([$access->user])->call('selectStudent', $access->user_id)->assertSee($access->course->title);
    }

    public function test_staff_can_edit_course_and_collapsed_lesson_in_one_panel(): void
    {
        $course = SelfStudyCourse::factory()->create();
        $lesson = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id]);
        $this->actingAs($course->administrator);
        Livewire::test(CourseCatalog::class)->callTableAction('edit', $course, data: ['title' => 'Обновлённое название', 'slug' => $course->slug, 'is_published' => true])->assertHasNoTableActionErrors();
        $this->assertSame('Обновлённое название', $course->fresh()->title);
        $this->assertTrue($lesson->fresh()->exists);
    }

    public function test_catalog_import_does_not_replace_existing_edits_or_create_students(): void
    {
        $this->seed(SelfStudyCatalogSeeder::class);
        $course = SelfStudyCourse::where('slug', 'power-query')->firstOrFail();
        $course->update(['title' => 'Редакторское название']);
        $this->seed(SelfStudyCatalogSeeder::class);
        $this->assertDatabaseCount('self_study_courses', 4);
        $this->assertDatabaseCount('self_study_lessons', 43);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame('Редакторское название', $course->fresh()->title);
        $this->assertDatabaseCount('self_study_enrollments', 0);
        $this->assertFalse($course->fresh()->is_published);
    }

    public function test_recorded_page_navigates_only_published_lessons_and_keeps_notes_on_the_selected_lesson(): void
    {
        $course = SelfStudyCourse::factory()->create(['kind' => 'recorded']);
        $access = SelfStudyEnrollment::factory()->create(['self_study_course_id' => $course->id]);
        $first = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id, 'position' => 1, 'module_title' => 'Первая тема']);
        $hidden = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id, 'position' => 2, 'title' => 'Неопубликованный урок', 'is_published' => false]);
        $last = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id, 'position' => 3, 'module_title' => 'Вторая тема']);
        $access->update(['completed_lessons' => [$first->id]]);

        $this->actingAs($access->user)->get(route('student.lesson', [$course, $first]))
            ->assertOk()->assertSee('Первая тема')->assertSee('Вторая тема')
            ->assertSee(route('student.lesson', [$course, $last]).'#rc-learning', false)
            ->assertDontSee('Неопубликованный урок')->assertDontSee('Предыдущий урок');
        $this->get(route('student.course', $course))->assertOk()->assertSee('Урок 2 из 2')
            ->assertSee('Предыдущий урок')->assertDontSee('Следующий урок');
        $this->post(route('student.lesson.note', [$course, $last]), ['note' => 'Применить в отчёте'])
            ->assertRedirect(route('student.lesson', [$course, $last]));
        $this->get(route('student.lesson', [$course, $last]))->assertOk()->assertSee('Применить в отчёте');
        $this->get(route('student.lesson', [$course, $first]))->assertOk()->assertDontSee('Применить в отчёте');
        $this->get(route('student.lesson', [$course, $hidden]))->assertNotFound();
    }

    public function test_assigned_staff_can_preview_draft_paid_lessons_without_opening_them_to_students(): void
    {
        $course = SelfStudyCourse::factory()->create(['kind' => 'recorded']);
        $lesson = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id, 'is_published' => false]);
        $access = SelfStudyEnrollment::factory()->create(['self_study_course_id' => $course->id]);
        $this->actingAs($course->administrator)->get(route('student.course', $course))->assertOk()->assertSee($lesson->title)->assertSee('Черновик урока');
        $this->get(route('student.lesson', [$course, $lesson]))->assertOk()->assertDontSee('data-progress-form', false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('student.lesson', [$course, $lesson]))->assertForbidden();
        $this->actingAs($access->user)->get(route('student.lesson', [$course, $lesson]))->assertNotFound();
        $this->postJson(route('student.lesson.progress', [$course, $lesson]), ['completed' => true])->assertNotFound();
    }
}
