<!doctype html><html lang="ru"><head><meta charset="utf-8"></head><body style="margin:0;background:#f3f6f7;font-family:Arial,sans-serif;color:#193946"><div style="max-width:600px;margin:24px auto;background:white;padding:32px">
<p style="font-weight:bold">ВЭШ</p>
@if(config('course_integration.sandbox'))<p style="background:#fff0c9;padding:12px">Тест нового личного кабинета. Письмо не относится к действующим учебным группам.</p>@endif
<h1 style="font-size:26px">{{ $mail->kind === 'reset' ? 'Восстановление пароля' : ($mail->kind === 'setup' ? 'Добро пожаловать в ЛК' : 'Курс уже в вашем кабинете') }}</h1>
<p>Здравствуйте, {{ $data['name'] }}!</p>
@if(isset($data['course']))<p>Вам открыт курс «{{ $data['course'] }}».</p><p>Доступ до {{ $data['expires'] }} по времени Екатеринбурга.</p>@endif
<p>{{ $mail->kind === 'access' ? 'Войдите с привычным паролем. Все ваши обучения остаются в одном аккаунте.' : 'Нажмите кнопку и задайте свой пароль.' }}</p>
<p style="margin:32px 0"><a href="{{ $data['url'] }}" style="background:#236e8a;color:white;padding:16px 24px;text-decoration:none">{{ $mail->kind === 'access' ? 'Открыть курс' : ($mail->kind === 'setup' ? 'Установить пароль' : 'Создать новый пароль') }}</a></p>
@if($mail->kind !== 'access')<p>Ссылка действует {{ $mail->kind === 'setup' ? '24 часа' : '60 минут' }}. После установки пароля её нельзя использовать повторно. Не пересылайте это письмо.</p><p>Если ссылка устарела, запросите новую на странице входа. Если вы не запрашивали письмо, просто проигнорируйте его.</p>@endif
<p>С заботой о вашем обучении,<br>Высшая экономическая школа</p></div></body></html>
