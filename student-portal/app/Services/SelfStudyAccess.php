<?php

namespace App\Services;

use App\Models\CourseOrder;
use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SelfStudyAccess
{
    public function grantFree(SelfStudyCourse $course, User $student, User $actor): SelfStudyEnrollment
    {
        abort_unless($course->canManage($actor) && $student->role === 'student', 403);
        $this->require($course->kind === 'free', 'Бесплатный доступ можно открыть только к бесплатному курсу.');

        return DB::transaction(function () use ($course, $student, $actor): SelfStudyEnrollment {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $existing = SelfStudyEnrollment::where('user_id', $student->id)->where('self_study_course_id', $course->id)->first();
            if ($existing) {
                return $existing;
            }
            $start = now();

            return SelfStudyEnrollment::create([
                'user_id' => $student->id, 'self_study_course_id' => $course->id,
                'starts_at' => $start, 'expires_at' => $start->copy()->timezone('Asia/Yekaterinburg')->addMonthNoOverflow()->timezone(config('app.timezone')),
                'completed_lessons' => [], 'notes' => [], 'history' => [$this->event($actor, 'Доступ открыт на месяц')],
            ]);
        });
    }

    public function createOrder(SelfStudyCourse $course, User $student, User $actor): CourseOrder
    {
        abort_unless($course->canManage($actor) && $student->role === 'student', 403);
        $this->require($course->kind === 'recorded' && $course->price_minor > 0 && $course->access_days > 0, 'Укажите цену и срок доступа курса в записи.');

        return DB::transaction(function () use ($course, $student): CourseOrder {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $this->require(! SelfStudyEnrollment::where('user_id', $student->id)->where('self_study_course_id', $course->id)->exists(), 'Доступ уже существует. Для продления откройте его карточку.');

            return CourseOrder::firstOrCreate(
                ['user_id' => $student->id, 'self_study_course_id' => $course->id, 'status' => 'pending'],
                ['amount_minor' => $course->price_minor, 'currency' => 'RUB', 'access_days' => $course->access_days],
            );
        });
    }

    public function confirmPayment(CourseOrder $order, User $actor, string $reference, int $amountMinor, string $paidAt): SelfStudyEnrollment
    {
        abort_unless($order->course->canManage($actor), 403);
        $reference = trim($reference);
        $this->require($reference !== '' && mb_strlen($reference) <= 180, 'Укажите идентификатор проверенного платежа.');

        return DB::transaction(function () use ($order, $actor, $reference, $amountMinor, $paidAt): SelfStudyEnrollment {
            $order = CourseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'paid') {
                $this->require($order->payment_reference === $reference && $order->amount_minor === $amountMinor, 'У этого заказа уже подтверждён другой платёж.');

                return SelfStudyEnrollment::where('course_order_id', $order->id)->firstOrFail();
            }
            $this->require($order->status === 'pending' && $order->currency === 'RUB' && $order->amount_minor === $amountMinor, 'Сумма платежа не совпадает с заказом.');
            $this->require(! CourseOrder::where('payment_reference', $reference)->exists(), 'Этот платёж уже использован в другом заказе.');
            $date = Carbon::parse($paidAt, 'Asia/Yekaterinburg')->timezone(config('app.timezone'));
            $this->require($date->lte(now()), 'Дата платежа не может быть в будущем.');
            User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $this->require(! SelfStudyEnrollment::where('user_id', $order->user_id)->where('self_study_course_id', $order->self_study_course_id)->exists(), 'Доступ уже существует. Проверьте заказ перед подтверждением.');
            $order->update(['status' => 'paid', 'payment_reference' => $reference, 'paid_at' => $date, 'confirmed_by' => $actor->id]);

            return SelfStudyEnrollment::create([
                'user_id' => $order->user_id, 'self_study_course_id' => $order->self_study_course_id, 'course_order_id' => $order->id,
                'starts_at' => $date, 'expires_at' => $date->copy()->addDays($order->access_days),
                'completed_lessons' => [], 'notes' => [], 'history' => [$this->event($actor, 'Оплата проверена, доступ открыт', 'Заказ '.$order->id)],
            ]);
        });
    }

    public function extend(SelfStudyEnrollment $enrollment, User $actor, string $until, string $reason): void
    {
        abort_unless($enrollment->course->canManage($actor), 403);
        $this->require(trim($reason) !== '', 'Укажите причину продления.');
        DB::transaction(function () use ($enrollment, $actor, $until, $reason): void {
            $enrollment = SelfStudyEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $date = Carbon::parse($until, 'Asia/Yekaterinburg')->endOfDay()->timezone(config('app.timezone'));
            $this->require(! $enrollment->revoked_at && $date->gt(now()) && $date->gt($enrollment->expires_at), 'Новая дата должна быть позже текущей даты окончания. Закрытый доступ продлить нельзя.');
            $enrollment->update(['expires_at' => $date, 'history' => [...($enrollment->history ?? []), $this->event($actor, 'Продление до '.$date->format('d.m.Y'), $reason)]]);
        });
    }

    public function revoke(SelfStudyEnrollment $enrollment, User $actor, string $reason): void
    {
        abort_unless($enrollment->course->canManage($actor), 403);
        $this->require(trim($reason) !== '', 'Укажите причину закрытия.');
        DB::transaction(function () use ($enrollment, $actor, $reason): void {
            $enrollment = SelfStudyEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if (! $enrollment->revoked_at) {
                $enrollment->update(['revoked_at' => now(), 'history' => [...($enrollment->history ?? []), $this->event($actor, 'Доступ закрыт', $reason)]]);
            }
        });
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['access' => $message]);
        }
    }

    private function event(User $actor, string $action, string $reason = ''): array
    {
        return ['at' => now()->toIso8601String(), 'actor_id' => $actor->id, 'action' => $action, 'reason' => $reason];
    }
}
