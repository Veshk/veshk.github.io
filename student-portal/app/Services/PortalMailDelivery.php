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
        if (! config('course_integration.mail_enabled') || ! config('course_integration.enkod_key') || ! config('course_integration.enkod_message_id')) {
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
        $template = (int) config('course_integration.enkod_message_id');
        try {
            $response = Http::connectTimeout(5)->timeout(15)->withHeaders(['apiKey' => config('course_integration.enkod_key')])
                ->post('https://api.enkod.ru/v1/mail/', [
                    'messageId' => $template, 'email' => $data['email'], 'snippets' => $this->snippets($mail),
                ]);
            if ($response->successful() && ($response->json() === 'Success' || trim($response->body()) === 'Success')) {
                $mail->update(['status' => 'accepted', 'provider_id' => 'message:'.$template, 'accepted_at' => now(), 'error' => null]);
            } else {
                $mail->update(['status' => $response->status() >= 500 || $response->successful() ? 'unknown' : 'rejected', 'error' => 'enKod HTTP '.$response->status()]);
            }
        } catch (Throwable) {
            $mail->update(['status' => 'unknown', 'error' => 'Не получен однозначный ответ enKod; проверить доставку перед повтором']);
        }
    }

    /** @return array<string, string> */
    private function snippets(PortalMail $mail): array
    {
        $data = $mail->payload;

        return [
            'heading' => $mail->kind === 'reset' ? 'Восстановление пароля' : ($mail->kind === 'setup' ? 'Добро пожаловать в ЛК' : 'Курс уже в вашем кабинете'),
            'name' => e($data['name']),
            'course_line' => isset($data['course']) ? e('Вам открыт курс «'.$data['course'].'».') : '',
            'access_line' => isset($data['expires']) ? e('Доступ до '.$data['expires'].' по времени Екатеринбурга.') : '',
            'intro' => $mail->kind === 'access' ? 'Войдите с привычным паролем. Все ваши обучения остаются в одном аккаунте.' : 'Нажмите кнопку и задайте свой пароль.',
            'action_url' => $data['url'],
            'action_label' => $mail->kind === 'access' ? 'Открыть курс' : ($mail->kind === 'setup' ? 'Установить пароль' : 'Создать новый пароль'),
            'link_hint' => $mail->kind === 'access' ? '' : 'Ссылка действует '.($mail->kind === 'setup' ? '24 часа' : '60 минут').'. После установки пароля её нельзя использовать повторно. Не пересылайте это письмо.',
            'login_url' => rtrim(config('app.url'), '/').'/login',
        ];
    }
}
