<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\EnrollmentApplication;
use App\Models\MailPreview;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class Admissions
{
    public function submit(User $user, ?int $groupId, ?string $message = null): EnrollmentApplication
    {
        return DB::transaction(function () use ($user, $groupId, $message): EnrollmentApplication {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($groupId) {
                $group = StudyGroup::query()->lockForUpdate()->findOrFail($groupId);
                if (! $group->registration_open) {
                    throw ValidationException::withMessages(['study_group_id' => 'Приём заявок в эту группу закрыт.']);
                }
                if (Enrollment::where('user_id', $user->id)->where('study_group_id', $groupId)->exists()) {
                    throw ValidationException::withMessages(['study_group_id' => 'Вы уже зачислены в эту группу.']);
                }
            }

            return EnrollmentApplication::firstOrCreate(
                ['user_id' => $user->id, 'study_group_id' => $groupId, 'status' => 'pending'],
                ['message' => $message]
            );
        });
    }

    public function approve(User $actor, EnrollmentApplication $application, bool $sendEmail): void
    {
        DB::transaction(function () use ($actor, $application, $sendEmail): void {
            $application = EnrollmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            Gate::forUser($actor)->authorize('review', $application);
            if ($application->status !== 'pending') {
                return;
            }
            if (! $application->study_group_id) {
                throw ValidationException::withMessages(['group' => 'Сначала определите группу слушателя.']);
            }
            Enrollment::firstOrCreate(['user_id' => $application->user_id, 'study_group_id' => $application->study_group_id]);
            $application->update(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'send_email' => $sendEmail]);
            if ($sendEmail) {
                MailPreview::firstOrCreate(['enrollment_application_id' => $application->id], [
                    'recipient' => $application->user->email,
                    'subject' => 'ВЭШ — доступ к обучению открыт',
                    'body' => view('mail.access-granted', ['application' => $application])->render(),
                ]);
            }
        });
    }

    public function reject(User $actor, EnrollmentApplication $application, string $note): void
    {
        DB::transaction(function () use ($actor, $application, $note): void {
            $application = EnrollmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            Gate::forUser($actor)->authorize('review', $application);
            if ($application->status !== 'pending') {
                return;
            }
            $application->update(['status' => 'rejected', 'decision_note' => $note, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'send_email' => false]);
        });
    }

    public function assign(User $actor, EnrollmentApplication $application, int $groupId): void
    {
        DB::transaction(function () use ($actor, $application, $groupId): void {
            $application = EnrollmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            Gate::forUser($actor)->authorize('review', $application);
            if ($application->status !== 'pending') {
                return;
            }
            StudyGroup::findOrFail($groupId);
            $duplicate = EnrollmentApplication::where('user_id', $application->user_id)->where('study_group_id', $groupId)->where('status', 'pending')->whereKeyNot($application->id)->exists();
            if ($duplicate || Enrollment::where('user_id', $application->user_id)->where('study_group_id', $groupId)->exists()) {
                throw ValidationException::withMessages(['study_group_id' => 'У слушателя уже есть заявка или доступ к этой группе.']);
            }
            $application->update(['study_group_id' => $groupId, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });
    }
}
