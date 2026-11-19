<?php

namespace App\Services;

use App\Models\CourseRegistrationEvent;
use App\Models\PortalMail;
use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseRegistration
{
    public function process(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $event = CourseRegistrationEvent::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($event->status !== 'received') {
                return;
            }
            $payload = $event->payload;
            $course = SelfStudyCourse::whereKey($payload['course_id'])->where('kind', 'free')->where('is_published', true)->first();
            if (! $course || (config('course_integration.sandbox') && $payload['email'] !== config('course_integration.allowed_email'))) {
                $event->update(['status' => 'blocked', 'error' => 'Курс или тестовый адрес не разрешён']);

                return;
            }
            $user = User::firstOrCreate(['email' => $payload['email']], ['name' => $payload['name'], 'password' => Str::random(64)]);
            $created = $user->wasRecentlyCreated;
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($created) {
                $user->forceFill(['password_setup_pending' => true])->save();
            }
            if ($user->role !== 'student') {
                $event->update(['status' => 'blocked', 'error' => 'Адрес принадлежит сотруднику']);

                return;
            }
            $enrollment = SelfStudyEnrollment::where('user_id', $user->id)->where('self_study_course_id', $course->id)->first();
            $isNew = ! $enrollment;
            if ($isNew) {
                $start = now();
                $enrollment = SelfStudyEnrollment::create([
                    'user_id' => $user->id, 'self_study_course_id' => $course->id,
                    'starts_at' => $start, 'expires_at' => $start->copy()->timezone('Asia/Yekaterinburg')->addMonthNoOverflow()->timezone(config('app.timezone')),
                    'completed_lessons' => [], 'notes' => [],
                    'history' => [['at' => $start->toIso8601String(), 'actor_id' => null, 'action' => 'Регистрация с лендинга', 'reason' => 'Событие '.$event->id]],
                ]);
            }
            if ($isNew) {
                $pending = $user->password_setup_pending;
                $url = $pending ? $this->passwordLink($user, 'setup') : route('student.course', $course);
                PortalMail::firstOrCreate(['deduplication_key' => 'course-access:'.$enrollment->id], [
                    'user_id' => $user->id, 'kind' => $pending ? 'setup' : 'access',
                    'payload' => ['email' => $user->email, 'name' => $user->name, 'course' => $course->title, 'expires' => $enrollment->expires_at->timezone('Asia/Yekaterinburg')->format('d.m.Y H:i'), 'url' => $url],
                ]);
            }
            $event->update(['status' => $isNew ? 'enrolled' : 'already_enrolled', 'user_id' => $user->id, 'self_study_enrollment_id' => $enrollment->id, 'processed_at' => now()]);
        });
    }

    public function passwordLink(User $user, string $purpose): string
    {
        $token = Str::random(64);
        DB::table('portal_password_tokens')->insert([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'purpose' => $purpose,
            'expires_at' => now()->addMinutes($purpose === 'setup' ? 1440 : 60), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return rtrim(config('app.url'), '/').route('portal.password.form', ['token' => $token], false);
    }
}
