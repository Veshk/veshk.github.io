<?php

namespace App\Services;

use App\Models\PortalMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class PortalMailDelivery
{
    public function send(int $id): void
    {
        if (! config('course_integration.mail_enabled') || ! config('course_integration.enkod_key')) {
            return;
        }
        $mail = DB::transaction(function () use ($id): ?PortalMail {
            $mail = PortalMail::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($mail->status !== 'pending') {
                return null;
            }
            if (config('course_integration.sandbox') && $mail->payload['email'] !== config('course_integration.allowed_email')) {
                $mail->update(['status' => 'blocked', 'error' => 'Получатель не разрешён']);

                return null;
            }
            $mail->update(['status' => 'sending']);

            return $mail;
        });
        if (! $mail) {
            return;
        }
        $data = $mail->payload;
        $subject = (config('course_integration.sandbox') ? '[ТЕСТ] ' : '').($mail->kind === 'reset' ? 'Восстановление пароля ЛК ВЭШ' : 'Ваш доступ к курсу в ЛК ВЭШ');
        try {
            $response = Http::connectTimeout(5)->timeout(15)->withBasicAuth('api', config('course_integration.enkod_key'))
                ->asForm()->post('https://api.enkod.ru/smtp/'.rawurlencode(config('course_integration.enkod_domain')).'/', [
                    'from' => config('course_integration.from'), 'to' => $data['email'], 'subject' => $subject,
                    'html' => view('emails.portal-access', ['mail' => $mail, 'data' => $data])->render(),
                ]);
            if ($response->successful() && is_string($response->json('id')) && $response->json('id') !== '') {
                $mail->update(['status' => 'accepted', 'provider_id' => $response->json('id'), 'accepted_at' => now(), 'error' => null]);
            } else {
                $mail->update(['status' => $response->status() >= 500 ? 'unknown' : 'rejected', 'error' => 'enKod HTTP '.$response->status()]);
            }
        } catch (Throwable) {
            $mail->update(['status' => 'unknown', 'error' => 'Не получен однозначный ответ enKod; проверить доставку перед повтором']);
        }
    }
}
