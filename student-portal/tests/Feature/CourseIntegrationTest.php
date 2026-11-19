<?php

namespace Tests\Feature;

use App\Models\CourseRegistrationEvent;
use App\Models\PortalMail;
use App\Models\SelfStudyCourse;
use App\Models\User;
use App\Services\CourseRegistration;
use App\Services\PortalMailDelivery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CourseIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['course_integration.enabled' => true, 'course_integration.sandbox' => true, 'course_integration.enkod_message_id' => 42,
            'course_integration.allowed_email' => 'pilot@example.test', 'course_integration.webhook_key' => str_repeat('k', 40),
            'course_integration.form_ids' => ['form123'], 'course_integration.course_slug' => 'test-power-query',
            'course_integration.project_id' => 'test-project', 'course_integration.page_id' => 'test-page']);
    }

    private function submit(array $overrides = []): TestResponse
    {
        return $this->postJson('/integrations/tilda/free-course', [...['tranid' => 'transaction-1', 'formid' => 'form123', 'email' => 'pilot@example.test', 'name' => 'Тестовый слушатель', 'Checkbox' => 'yes'], ...$overrides], ['X-Portal-Key' => str_repeat('k', 40)]);
    }

    private function course(): SelfStudyCourse
    {
        return SelfStudyCourse::factory()->create(['slug' => 'test-power-query']);
    }

    public function test_disabled_and_untrusted_webhooks_cannot_create_data(): void
    {
        config(['course_integration.enabled' => false]);
        $this->submit()->assertNotFound();
        config(['course_integration.enabled' => true]);
        $this->postJson('/integrations/tilda/free-course', ['test' => 'test'])->assertUnauthorized();
        $this->assertDatabaseCount('course_registration_events', 0);
    }

    public function test_connection_probe_does_not_enroll_or_send(): void
    {
        $this->postJson('/integrations/tilda/free-course', ['test' => 'test'], ['X-Portal-Key' => str_repeat('k', 40)])->assertOk();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('portal_mails', 0);
    }

    public function test_allowlist_form_consent_and_course_kind_are_enforced(): void
    {
        $course = $this->course();
        $this->submit(['email' => 'real-student@example.test'])->assertForbidden();
        $this->submit(['formid' => 'other'])->assertUnprocessable();
        $this->submit(['Checkbox' => 'no'])->assertUnprocessable();
        $course->update(['kind' => 'recorded']);
        $this->submit()->assertNotFound();
        $this->assertDatabaseCount('course_registration_events', 0);
    }

    public function test_durable_event_is_idempotent_and_conflicting_replay_is_quarantined(): void
    {
        $this->course();
        $this->submit()->assertOk();
        $this->submit()->assertOk();
        $this->assertDatabaseCount('course_registration_events', 1);
        $this->assertDatabaseCount('self_study_enrollments', 0);
        $this->submit(['name' => 'Другой человек'])->assertStatus(409);
        app(CourseRegistration::class)->process(CourseRegistrationEvent::first()->id);
        $this->assertDatabaseCount('self_study_enrollments', 0);
    }

    public function test_first_registration_creates_one_pending_account_month_access_and_outbox(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(10, 0));
        $this->course();
        $this->submit(['email' => 'PILOT@EXAMPLE.TEST'])->assertOk();
        $event = CourseRegistrationEvent::first();
        app(CourseRegistration::class)->process($event->id);
        app(CourseRegistration::class)->process($event->id);
        $user = User::where('email', 'pilot@example.test')->firstOrFail();
        $this->assertTrue($user->password_setup_pending);
        $this->assertSame('student', $user->role);
        $this->assertSame('2026-02-28 10:00', $user->selfStudyEnrollments()->first()->expires_at->format('Y-m-d H:i'));
        $this->assertDatabaseCount('self_study_enrollments', 1);
        $this->assertDatabaseCount('portal_mails', 1);
        $this->assertDatabaseCount('enrollment_applications', 0);
        $this->assertSame('setup', PortalMail::first()->kind);
        $this->assertStringNotContainsString('pilot@example.test', DB::table('course_registration_events')->first()->payload);
        $this->assertStringNotContainsString('/password/set/', DB::table('portal_mails')->first()->payload);
    }

    public function test_existing_account_keeps_password_and_expired_access_and_progress(): void
    {
        $this->course();
        $user = User::factory()->create(['email' => 'pilot@example.test', 'password' => 'Original12345']);
        $this->submit()->assertOk();
        app(CourseRegistration::class)->process(CourseRegistrationEvent::first()->id);
        $this->assertFalse($user->fresh()->password_setup_pending);
        $this->assertTrue(Hash::check('Original12345', $user->fresh()->password));
        $this->assertSame('access', PortalMail::first()->kind);
        $access = $user->selfStudyEnrollments()->first();
        $access->update(['expires_at' => now()->subDay(), 'completed_lessons' => [7], 'feedback_rating' => 5]);
        $expiry = $access->fresh()->expires_at->toIso8601String();
        $this->submit(['tranid' => 'transaction-2'])->assertOk();
        app(CourseRegistration::class)->process(CourseRegistrationEvent::latest('id')->first()->id);
        $this->assertSame($expiry, $access->fresh()->expires_at->toIso8601String());
        $this->assertSame([7], $access->fresh()->completed_lessons);
        $this->assertSame(5, $access->fresh()->feedback_rating);
        $this->assertDatabaseCount('portal_mails', 1);
    }

    public function test_staff_is_not_converted_to_student(): void
    {
        $this->course();
        $user = User::factory()->create(['email' => 'pilot@example.test', 'role' => 'senior']);
        $this->submit()->assertOk();
        app(CourseRegistration::class)->process(CourseRegistrationEvent::first()->id);
        $this->assertSame('senior', $user->fresh()->role);
        $this->assertDatabaseCount('self_study_enrollments', 0);
        $this->assertDatabaseCount('portal_mails', 0);
    }

    public function test_password_link_is_one_time_and_does_not_grant_extra_enrollments(): void
    {
        $user = User::factory()->create(['email' => 'pilot@example.test', 'password_setup_pending' => true]);
        $url = app(CourseRegistration::class)->passwordLink($user, 'setup');
        $this->get($url)->assertOk()->assertSee('Установите пароль')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post($url, ['password' => 'StrongTest123', 'password_confirmation' => 'StrongTest123'])->assertRedirect('/learning');
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->fresh()->password_setup_pending);
        $this->assertTrue(Hash::check('StrongTest123', $user->fresh()->password));
        $this->post($url, ['password' => 'OtherTest123', 'password_confirmation' => 'OtherTest123'])->assertUnprocessable();
        $this->assertDatabaseCount('self_study_enrollments', 0);
    }

    public function test_expired_token_and_other_email_cannot_change_password(): void
    {
        $user = User::factory()->create(['email' => 'pilot@example.test']);
        $url = app(CourseRegistration::class)->passwordLink($user, 'reset');
        $this->travel(61)->minutes();
        $this->get($url)->assertOk()->assertSee('Ссылка больше не действует');
        $this->post($url, ['password' => 'StrongTest123', 'password_confirmation' => 'StrongTest123'])->assertUnprocessable();
        $other = User::factory()->create();
        $otherUrl = app(CourseRegistration::class)->passwordLink($other, 'reset');
        $this->post($otherUrl, ['password' => 'StrongTest123', 'password_confirmation' => 'StrongTest123'])->assertForbidden();
    }

    public function test_recovery_uses_neutral_response_and_does_not_send_to_unapproved_email(): void
    {
        $user = User::factory()->create(['email' => 'pilot@example.test']);
        $this->post('/password/request', ['email' => 'unknown@example.test'])->assertSessionHas('status');
        $this->assertDatabaseCount('portal_mails', 0);
        $this->post('/password/request', ['email' => $user->email])->assertSessionHas('status');
        $this->post('/password/request', ['email' => $user->email])->assertSessionHas('status');
        $this->assertDatabaseCount('portal_mails', 1);
    }

    public function test_outbox_acceptance_is_distinguished_from_delivery_and_send_is_once(): void
    {
        config(['course_integration.mail_enabled' => true, 'course_integration.enkod_key' => 'test-key', 'course_integration.enkod_domain' => 'example.test', 'course_integration.from' => 'portal@example.test']);
        $user = User::factory()->create(['email' => 'pilot@example.test']);
        $mail = PortalMail::create(['user_id' => $user->id, 'kind' => 'reset', 'deduplication_key' => 'test-mail', 'payload' => ['email' => $user->email, 'name' => $user->name, 'url' => 'https://example.test/password/set/token']]);
        Http::fake(['*' => Http::response('Success', 200)]);
        app(PortalMailDelivery::class)->send($mail->id);
        app(PortalMailDelivery::class)->send($mail->id);
        Http::assertSentCount(1);
        $this->assertSame('accepted', $mail->fresh()->status);
        $this->assertSame('message:42', $mail->fresh()->provider_id);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.enkod.ru/v1/mail/' && $request->hasHeader('apiKey', 'test-key') && $request['email'] === 'pilot@example.test' && $request['messageId'] === 42 && $request['snippets']['action_url'] === 'https://example.test/password/set/token');
    }

    public function test_uncertain_mail_is_not_automatically_retried(): void
    {
        config(['course_integration.mail_enabled' => true, 'course_integration.enkod_key' => 'test-key']);
        $user = User::factory()->create(['email' => 'pilot@example.test']);
        $mail = PortalMail::create(['user_id' => $user->id, 'kind' => 'reset', 'deduplication_key' => 'test-mail', 'payload' => ['email' => $user->email, 'name' => 'Тест', 'url' => 'https://example.test']]);
        Http::fake(['*' => Http::response([], 500)]);
        app(PortalMailDelivery::class)->send($mail->id);
        app(PortalMailDelivery::class)->send($mail->id);
        Http::assertSentCount(1);
        $this->assertSame('unknown', $mail->fresh()->status);
    }

    public function test_unexpected_success_response_is_not_retried_and_snippets_escape_html(): void
    {
        config(['course_integration.mail_enabled' => true, 'course_integration.enkod_key' => 'test-key']);
        $user = User::factory()->create(['email' => 'pilot@example.test']);
        $mail = PortalMail::create(['user_id' => $user->id, 'kind' => 'setup', 'deduplication_key' => 'test-mail', 'payload' => ['email' => $user->email, 'name' => '<img src=x>', 'url' => 'https://example.test/password/set/token']]);
        Http::fake(['*' => Http::response(['unexpected' => true], 200)]);
        app(PortalMailDelivery::class)->send($mail->id);
        app(PortalMailDelivery::class)->send($mail->id);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['snippets']['name'] === '&lt;img src=x&gt;');
        $this->assertSame('unknown', $mail->fresh()->status);
    }

    public function test_mail_transport_also_enforces_allowlist(): void
    {
        config(['course_integration.mail_enabled' => true, 'course_integration.enkod_key' => 'test-key']);
        $user = User::factory()->create();
        $mail = PortalMail::create(['user_id' => $user->id, 'kind' => 'reset', 'deduplication_key' => 'test-mail', 'payload' => ['email' => $user->email]]);
        Http::fake();
        app(PortalMailDelivery::class)->send($mail->id);
        Http::assertNothingSent();
        $this->assertSame('blocked', $mail->fresh()->status);
    }

    public function test_sandbox_blocks_regular_registration_and_guide_requires_authorization(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/technical/integrations')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/technical/integrations')->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'pilot@example.test']))->get('/technical/integrations')->assertOk()->assertSee('Этот тест изолирован');
    }
}
