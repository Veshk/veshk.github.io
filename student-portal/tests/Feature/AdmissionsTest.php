<?php

namespace Tests\Feature;

use App\Filament\Pages\Reviewers;
use App\Filament\Pages\StaffProfiles;
use App\Filament\Resources\EnrollmentApplications\Pages\ListEnrollmentApplications;
use App\Models\Enrollment;
use App\Models\EnrollmentApplication;
use App\Models\MailPreview;
use App\Models\StudyGroup;
use App\Models\User;
use App\Services\Admissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdmissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_merge(['last_name' => 'Тестова', 'first_name' => 'Анна', 'patronymic' => '', 'email' => 'new@vesh.test', 'password' => 'TestPass2026!', 'password_confirmation' => 'TestPass2026!'], $overrides);
    }

    public function test_registration_creates_pending_application_without_material_access(): void
    {
        $group = StudyGroup::factory()->create();
        $this->post('/register', $this->registration(['study_group_id' => $group->id, 'role' => 'senior', 'reviews_unassigned' => true]))->assertRedirect('/learning');
        $user = User::where('email', 'new@vesh.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('student', $user->role);
        $this->assertFalse($user->reviews_unassigned);
        $this->assertTrue(Hash::check('TestPass2026!', $user->password));
        $this->assertDatabaseHas('enrollment_applications', ['user_id' => $user->id, 'study_group_id' => $group->id, 'status' => 'pending']);
        $this->assertDatabaseCount('enrollments', 0);
        $this->get('/learning')->assertOk()->assertSee('Заявка на проверке');
        $this->get('/learning/'.$group->id)->assertForbidden();
        $this->get('/learning/'.$group->id.'/materials/0')->assertForbidden();
    }

    public function test_closed_registration_is_hidden_and_cannot_be_submitted(): void
    {
        $group = StudyGroup::factory()->create(['title' => 'Скрытая группа', 'registration_open' => false]);
        $this->get('/register')->assertOk()->assertDontSee('Скрытая группа');
        $this->post('/register', $this->registration(['study_group_id' => $group->id]))->assertSessionHasErrors(['study_group_id' => 'Приём заявок в эту группу закрыт.']);
        $this->assertDatabaseMissing('users', ['email' => 'new@vesh.test']);
        $this->assertDatabaseCount('enrollment_applications', 0);
    }

    public function test_registration_without_group_creates_help_request(): void
    {
        $this->post('/register', $this->registration(['study_group_id' => '', 'message' => 'Не помню группу']))->assertRedirect('/learning');
        $this->assertDatabaseHas('enrollment_applications', ['study_group_id' => null, 'message' => 'Не помню группу', 'status' => 'pending']);
        $this->get('/learning')->assertOk()->assertSee('Помощь с выбором группы');
    }

    public function test_existing_email_is_normalized_without_duplicate_account_or_password_change(): void
    {
        $user = User::factory()->create(['email' => 'new@vesh.test']);
        $original = $user->password;
        $this->post('/register', $this->registration(['email' => 'NEW@vesh.test']))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($original, $user->fresh()->password);
        $this->assertDatabaseCount('enrollment_applications', 0);
    }

    public function test_registration_validates_required_fields_and_password_confirmation(): void
    {
        $this->post('/register', [])->assertSessionHasErrors(['last_name', 'first_name', 'email', 'password']);
        $this->post('/register', $this->registration(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_repeated_application_keeps_existing_course_and_does_not_duplicate_pending_request(): void
    {
        $user = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['user_id' => $user->id]);
        $newGroup = StudyGroup::factory()->create();
        $this->actingAs($user)->post('/applications', ['study_group_id' => $newGroup->id])->assertRedirect('/learning?list=1');
        $this->post('/applications', ['study_group_id' => $newGroup->id])->assertRedirect();
        $this->assertDatabaseCount('enrollment_applications', 1);
        $this->get('/learning/'.$enrollment->study_group_id)->assertOk();
        $this->get('/learning/'.$newGroup->id)->assertForbidden();
        $this->post('/applications', ['study_group_id' => $enrollment->study_group_id])->assertSessionHasErrors('study_group_id');
    }

    #[TestWith([true, 1])]
    #[TestWith([false, 0])]
    public function test_approval_opens_access_with_optional_captured_email_without_duplicates(bool $sendEmail, int $emails): void
    {
        $application = EnrollmentApplication::factory()->create();
        $actor = $application->group->administrator;
        Mail::fake();
        app(Admissions::class)->approve($actor, $application, $sendEmail);
        app(Admissions::class)->approve($actor, $application, true);
        $this->assertDatabaseHas('enrollment_applications', ['id' => $application->id, 'status' => 'approved', 'reviewed_by' => $actor->id, 'send_email' => $sendEmail]);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('mail_previews', $emails);
        $this->actingAs($application->user)->get('/learning/'.$application->study_group_id)->assertOk();
        Mail::assertNothingSent();
    }

    public function test_foreign_administrator_cannot_approve_even_via_service(): void
    {
        $application = EnrollmentApplication::factory()->create();
        $other = User::factory()->create(['role' => 'admin']);
        try {
            app(Admissions::class)->approve($other, $application, true);
            $this->fail('An unauthorized approval was accepted.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('enrollment_applications', ['id' => $application->id, 'status' => 'pending']);
        }
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('mail_previews', 0);
        $this->actingAs($other)->get('/admin/study-groups/'.$application->study_group_id)->assertOk()->assertSee('Только просмотр');
        $this->get('/admin/study-groups/'.$application->study_group_id.'/edit')->assertForbidden();
    }

    #[TestWith(['student', false, false])]
    #[TestWith(['admin', false, false])]
    #[TestWith(['admin', true, true])]
    #[TestWith(['senior', false, false])]
    #[TestWith(['senior', true, true])]
    public function test_unassigned_application_requires_designated_staff(string $role, bool $designated, bool $allowed): void
    {
        $application = EnrollmentApplication::factory()->create(['study_group_id' => null]);
        $actor = User::factory()->create(['role' => $role, 'reviews_unassigned' => $designated]);
        $this->assertSame($allowed, $actor->can('review', $application));
        $this->assertSame($allowed, $actor->can('view', $application));
    }

    public function test_assigned_helper_can_route_application_but_cannot_approve_foreign_group(): void
    {
        $application = EnrollmentApplication::factory()->create(['study_group_id' => null]);
        $actor = User::factory()->create(['role' => 'admin', 'reviews_unassigned' => true]);
        $group = StudyGroup::factory()->create();
        app(Admissions::class)->assign($actor, $application, $group->id);
        $this->assertDatabaseHas('enrollment_applications', ['id' => $application->id, 'study_group_id' => $group->id, 'status' => 'pending', 'reviewed_by' => $actor->id]);
        $this->assertFalse($actor->can('review', $application->fresh()));
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_rejected_application_never_opens_access_or_sends_email(): void
    {
        $application = EnrollmentApplication::factory()->create();
        app(Admissions::class)->reject($application->group->administrator, $application, 'Нужно уточнить группу');
        app(Admissions::class)->approve($application->group->administrator, $application, true);
        $this->assertDatabaseHas('enrollment_applications', ['id' => $application->id, 'status' => 'rejected']);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('mail_previews', 0);
        $this->actingAs($application->user)->get('/learning')->assertOk()->assertSee('Нужно уточнить группу');
    }

    public function test_admin_action_approves_and_respects_unchecked_email(): void
    {
        $application = EnrollmentApplication::factory()->create();
        $this->actingAs($application->group->administrator);
        Livewire::test(ListEnrollmentApplications::class)->callTableAction('approve', $application, ['send_email' => false]);
        $this->assertDatabaseHas('enrollment_applications', ['id' => $application->id, 'status' => 'approved', 'send_email' => false]);
        $this->assertDatabaseCount('mail_previews', 0);
    }

    public function test_senior_can_choose_multiple_responsible_staff(): void
    {
        $senior = User::factory()->create(['role' => 'senior']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($senior);
        Livewire::test(StaffProfiles::class)->set('reviewsUnassigned', true)->call('save')->assertHasNoErrors();
        Livewire::test(StaffProfiles::class)->call('selectPerson', $admin->id)->set('reviewsUnassigned', true)->call('save')->assertHasNoErrors();
        Livewire::test(Reviewers::class)->assertRedirect(StaffProfiles::getUrl());
        $this->assertTrue($senior->fresh()->reviews_unassigned);
        $this->assertTrue($admin->fresh()->reviews_unassigned);
        $this->actingAs($admin)->get('/admin/reviewers')->assertForbidden();
    }

    public function test_mail_preview_is_private_and_escapes_user_content(): void
    {
        $application = EnrollmentApplication::factory()->create();
        $application->user->update(['name' => '<script>alert(1)</script>']);
        app(Admissions::class)->approve($application->group->administrator, $application, true);
        $preview = MailPreview::sole();
        $this->actingAs($application->user)->get('/mail-preview/'.$preview->id)->assertForbidden();
        $this->actingAs($application->group->administrator)->get('/mail-preview/'.$preview->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_guest_and_student_cannot_open_administration(): void
    {
        $this->get('/learning')->assertRedirect('/login');
        $this->get('/admin/enrollment-applications')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/enrollment-applications')->assertForbidden();
    }

    public function test_expiration_and_individual_extension_are_checked_on_every_request(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 2)->startOfDay());
        $group = StudyGroup::factory()->create(['graduates_at' => '2026-11-01', 'access_days' => 30]);
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertForbidden();
        $enrollment->update(['extended_until' => '2026-12-10']);
        $this->get('/learning/'.$group->id)->assertOk();
        $this->travelTo(now()->setDate(2026, 12, 11));
        $this->get('/learning/'.$group->id)->assertForbidden();
    }

    public function test_demo_switching_is_unavailable_outside_local_environment(): void
    {
        config(['app.demo_mode' => true]);
        $this->app['env'] = 'production';
        $this->get('/demo')->assertNotFound();
        $token = csrf_token();
        $this->withSession(['_token' => $token])->post('/demo/senior', ['_token' => $token])->assertNotFound();
        $this->assertGuest();
    }

    public function test_login_normalizes_email_and_throttles_failed_attempts(): void
    {
        $user = User::factory()->create(['email' => 'student@vesh.test', 'password' => 'TestPass2026!']);
        $this->post('/login', ['email' => 'STUDENT@vesh.test', 'password' => 'TestPass2026!'])->assertRedirect('/learning');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors(['email' => 'Слишком много попыток. Попробуйте через минуту.']);
    }
}
