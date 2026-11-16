<?php

namespace Tests\Feature;

use App\Filament\Pages\CourseAccesses;
use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\SelfStudyLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseFeedbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_free_and_recorded_courses_end_with_feedback_and_hide_notes(): void
    {
        foreach (['free', 'recorded'] as $kind) {
            $course = SelfStudyCourse::factory()->create(['kind' => $kind]);
            $access = SelfStudyEnrollment::factory()->create(['self_study_course_id' => $course->id, 'notes' => ['1' => 'Старая заметка']]);
            $lesson = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id]);
            $this->actingAs($access->user)->get(route('student.lesson', [$course, $lesson]))
                ->assertOk()->assertSee('Перейти к анкете')->assertSee('Анкета обратной связи')
                ->assertDontSee('Мои заметки')->assertDontSee('Сохранить заметку')->assertDontSee('Шаблон заметок');
            $this->get(route('student.course.feedback', $course))->assertOk()
                ->assertSee('Как вы оцениваете курс?')->assertSee('Что понравилось?')->assertDontSee('data-progress-form', false);
            $this->post(route('student.course.feedback.store', $course), ['rating' => 5, 'liked' => 'Полезные примеры'])
                ->assertRedirect(route('student.course.feedback', $course));
            $this->post(route('student.course.feedback.store', $course), ['rating' => 4, 'improvements' => 'Больше практики'])->assertRedirect();
            $this->assertSame(4, $access->fresh()->feedback_rating);
            $this->assertNull($access->fresh()->feedback_liked);
            $this->assertSame('Больше практики', $access->fresh()->feedback_improvements);
            $this->assertSame([], $access->fresh()->completed_lessons);
            $this->assertSame(['1' => 'Старая заметка'], $access->fresh()->notes);
            $this->assertNotNull($access->fresh()->feedback_submitted_at);
            $this->get(route('student.course.feedback', $course))->assertOk()->assertSee('Ответ отправлен')->assertSee('Больше практики')->assertSee('Сохранить изменения');
        }
        $this->assertDatabaseCount('self_study_enrollments', 2);
    }

    public function test_feedback_validates_rating_and_comment_length(): void
    {
        $access = SelfStudyEnrollment::factory()->create();
        $this->actingAs($access->user);
        foreach ([null, 0, 6, 2.5, 'invalid'] as $rating) {
            $this->post(route('student.course.feedback.store', $access->course), ['rating' => $rating])->assertSessionHasErrors('rating');
        }
        $this->post(route('student.course.feedback.store', $access->course), ['rating' => 5, 'liked' => str_repeat('я', 5001), 'improvements' => str_repeat('я', 5001)])
            ->assertSessionHasErrors(['liked', 'improvements']);
        $this->assertNull($access->fresh()->feedback_submitted_at);
    }

    public function test_feedback_requires_own_active_access(): void
    {
        $access = SelfStudyEnrollment::factory()->create(['feedback_rating' => 5, 'feedback_liked' => 'Личный ответ', 'feedback_submitted_at' => now()]);
        $url = route('student.course.feedback', $access->course);
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden()->assertDontSee('Личный ответ');
        $this->post($url, ['rating' => 1])->assertForbidden();
        $this->actingAs($access->user);
        foreach ([['expires_at' => now()->subMinute()], ['starts_at' => now()->addDay()], ['revoked_at' => now()]] as $change) {
            $access->update(array_merge(['expires_at' => now()->addMonth(), 'starts_at' => now()->subDay(), 'revoked_at' => null], $change));
            $this->get($url)->assertForbidden();
            $this->post($url, ['rating' => 1])->assertForbidden();
        }
        $access->update(['revoked_at' => null]);
        $access->course->update(['is_published' => false]);
        $this->post($url, ['rating' => 1])->assertForbidden();
        $this->assertSame(5, $access->fresh()->feedback_rating);
    }

    public function test_staff_preview_cannot_submit_feedback(): void
    {
        $course = SelfStudyCourse::factory()->create();
        $this->actingAs($course->administrator)->get(route('student.course.feedback', $course))->assertOk()
            ->assertSee('Форма доступна для заполнения в режиме слушателя')->assertSee('disabled', false);
        $this->post(route('student.course.feedback.store', $course), ['rating' => 5])->assertForbidden();
        $this->assertDatabaseCount('self_study_enrollments', 0);
    }

    public function test_responses_are_visible_only_to_responsible_staff_and_senior_and_are_escaped(): void
    {
        $own = SelfStudyEnrollment::factory()->create(['feedback_rating' => 4, 'feedback_liked' => '<script>alert(1)</script>', 'feedback_submitted_at' => now()]);
        $other = SelfStudyEnrollment::factory()->create(['feedback_rating' => 1, 'feedback_liked' => 'Ответ другого курса', 'feedback_submitted_at' => now()]);
        $this->actingAs($own->course->administrator);
        Livewire::test(CourseAccesses::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other])
            ->mountTableAction('feedback', $own)->assertMountedActionModalSee('4 из 5')->assertMountedActionModalSee('<script>alert(1)</script>')->assertMountedActionModalDontSeeHtml('<script>alert(1)</script>');
        $restricted = Livewire::test(CourseAccesses::class)->mountTableAction('feedback', $other);
        $this->assertNull($restricted->instance()->getMountedAction());
        $this->actingAs(User::factory()->create(['role' => 'senior']));
        Livewire::test(CourseAccesses::class)->assertCanSeeTableRecords([$own, $other]);
        $this->actingAs($own->user)->get('/admin/course-accesses')->assertForbidden();
    }
}
