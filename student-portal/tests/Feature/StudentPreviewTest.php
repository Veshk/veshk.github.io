<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\EnrollmentApplication;
use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\SelfStudyLesson;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_staff_can_enter_preview_and_subject_must_be_a_student(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->create(['role' => 'senior']);
        $this->get('/learning?preview=1')->assertRedirect('/login');
        $this->actingAs($student)->get('/learning?preview=1')->assertForbidden();
        $this->get('/learning?as_student='.$student->id)->assertForbidden();
        $this->actingAs($admin)->get('/learning?as_student='.$admin->id)->assertNotFound();
        $this->get('/learning?as_student=invalid')->assertNotFound();
        $this->get('/learning')->assertRedirect('/admin');
    }

    public function test_general_view_contains_all_groups_and_only_manageable_published_courses(): void
    {
        $group = StudyGroup::factory()->create(['title' => 'Группа другого администратора']);
        $own = SelfStudyCourse::factory()->create(['title' => 'Свой курс']);
        $other = SelfStudyCourse::factory()->create(['title' => 'Чужой курс']);
        SelfStudyCourse::factory()->create(['administrator_id' => $own->administrator_id, 'title' => 'Скрытый курс', 'is_published' => false]);
        $this->actingAs($own->administrator)->get('/learning?preview=1')->assertOk()
            ->assertSee($group->title)->assertSee($own->title)->assertDontSee($other->title)->assertDontSee('Скрытый курс')
            ->assertSee('Вы смотрите как студент')->assertSee('Вернуться в управление');
        $this->get(route('student.group', [$group, 'preview' => 1]))->assertOk()->assertDontSee('Редактировать страницу ЛК');
        $this->assertAuthenticatedAs($own->administrator);
        $this->assertDatabaseCount('portal_visits', 0);
    }

    public function test_personal_home_uses_subject_enrollments_applications_and_access_dates(): void
    {
        $access = Enrollment::factory()->create();
        $pending = StudyGroup::factory()->create(['title' => 'Ожидающая группа']);
        EnrollmentApplication::create(['user_id' => $access->user_id, 'study_group_id' => $pending->id, 'status' => 'pending']);
        $foreign = Enrollment::factory()->create(['study_group_id' => StudyGroup::factory()->create(['title' => 'Посторонняя группа'])->id]);
        $admin = User::factory()->create(['role' => 'senior']);
        $this->actingAs($admin)->get('/learning?as_student='.$access->user_id)->assertOk()
            ->assertSee($access->user->name)->assertSee($access->group->title)->assertSee($pending->title)
            ->assertDontSee($foreign->group->title)->assertSee('Ожидает проверки')->assertDontSee('Добавить обучение')
            ->assertSee(route('student.group', [$access->group, 'as_student' => $access->user_id]), false);
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseCount('portal_visits', 0);
        $this->assertDatabaseCount('enrollment_applications', 1);
    }

    public function test_personal_group_preview_enforces_enrollment_expiry_and_extension(): void
    {
        $access = Enrollment::factory()->create();
        $admin = $access->group->administrator;
        $url = route('student.group', [$access->group, 'as_student' => $access->user_id]);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Вы смотрите как студент')->assertDontSee('block-edit-tools');
        $this->get(route('student.group', [StudyGroup::factory()->create(), 'as_student' => $access->user_id]))->assertForbidden();
        $access->group->update(['graduates_at' => now()->subMonths(3)->toDateString()]);
        $this->get($url)->assertForbidden();
        $access->update(['extended_until' => now()->addDay()->toDateString()]);
        $this->get($url)->assertOk();
        $this->assertDatabaseCount('portal_visits', 0);
    }

    public function test_personal_course_preview_is_read_only_for_free_and_recorded_courses(): void
    {
        $admin = User::factory()->create(['role' => 'senior']);
        foreach (['free', 'recorded'] as $kind) {
            $course = SelfStudyCourse::factory()->create(['kind' => $kind]);
            $first = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id, 'position' => 1]);
            $second = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id, 'position' => 2]);
            $hidden = SelfStudyLesson::factory()->create(['self_study_course_id' => $course->id, 'title' => 'Черновой урок', 'is_published' => false]);
            $access = SelfStudyEnrollment::factory()->create(['self_study_course_id' => $course->id, 'completed_lessons' => [$first->id], 'feedback_rating' => 4]);
            $before = $access->fresh()->getRawOriginal();
            $query = ['as_student' => $access->user_id];
            $this->actingAs($admin)->get(route('student.course', [$course, ...$query]))->assertOk()
                ->assertSee('Вы смотрите как студент')->assertSee('50%')->assertDontSee('Черновой урок')->assertDontSee('data-progress-form', false)
                ->assertSee(route('student.lesson', [$course, $second, ...$query]), false);
            $this->get(route('student.course.feedback', [$course, ...$query]))->assertOk()->assertSee('disabled')->assertSee('Форма доступна для заполнения в режиме слушателя');
            $this->get(route('student.lesson', [$course, $hidden, ...$query]))->assertNotFound();
            foreach ([$query, []] as $parameters) {
                $this->postJson(route('student.lesson.progress', [$course, $second, ...$parameters]), ['completed' => true])->assertForbidden();
                $this->post(route('student.lesson.note', [$course, $second, ...$parameters]), ['note' => 'Чужая заметка'])->assertForbidden();
                $this->post(route('student.course.feedback.store', [$course, ...$parameters]), ['rating' => 1])->assertForbidden();
            }
            $this->assertSame($before, $access->fresh()->getRawOriginal());
            $access->update(['expires_at' => now()->subMinute()]);
            $this->get(route('student.course', [$course, ...$query]))->assertForbidden();
        }
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseCount('portal_visits', 0);
    }

    public function test_material_links_keep_subject_and_download_enforces_their_access(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('course-materials/example.pdf', 'private material');
        $access = SelfStudyEnrollment::factory()->create();
        $lesson = SelfStudyLesson::factory()->create(['self_study_course_id' => $access->self_study_course_id, 'materials' => [['title' => 'Материал', 'path' => 'course-materials/example.pdf']]]);
        $parameters = [$access->course, $lesson, 0, 'as_student' => $access->user_id];
        $url = route('student.lesson.material', $parameters);
        $this->actingAs($access->course->administrator)->get(route('student.lesson', [$access->course, $lesson, 'as_student' => $access->user_id]))->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk()->assertDownload('example.pdf');
        $access->update(['expires_at' => now()->subMinute()]);
        $this->get($url)->assertForbidden();
        $this->assertDatabaseCount('portal_visits', 0);
    }

    public function test_preview_does_not_expand_staff_course_permissions(): void
    {
        $access = SelfStudyEnrollment::factory()->create();
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($otherAdmin)->get(route('student.course', [$access->course, 'as_student' => $access->user_id]))->assertForbidden();
        $this->get(route('student.course', [$access->course, 'preview' => 1]))->assertForbidden();
        EnrollmentApplication::create(['user_id' => $access->user_id, 'status' => 'pending']);
        $this->get('/learning?as_student='.$access->user_id)->assertOk()->assertDontSee('Помощь с выбором группы');
        $otherAdmin->forceFill(['reviews_unassigned' => true])->save();
        $this->get('/learning?as_student='.$access->user_id)->assertOk()->assertSee('Помощь с выбором группы');
        $this->actingAs($access->course->administrator)->get(route('student.course', [$access->course, 'preview' => 1]))->assertOk();
        $access->course->update(['is_published' => false]);
        $this->get(route('student.course', [$access->course, 'preview' => 1]))->assertNotFound();
    }
}
