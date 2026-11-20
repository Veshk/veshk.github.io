@extends('layouts.portal', ['authLayout' => true])
@section('title', 'Пароль личного кабинета')
@section('content')
<main class="auth-page">@include('partials.story')<section class="auth-form-wrap"><div class="auth-form">
<div class="eyebrow muted">Личный кабинет ВЭШ</div>
<h2>{{ $mode === 'request' ? 'Получить ссылку для входа' : ($mode === 'expired' ? 'Ссылка больше не действует' : ($mode === 'setup' ? 'Установите пароль' : 'Новый пароль')) }}</h2>
@include('partials.errors')
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($mode === 'expired')
<p>Запросите новое письмо. Доступ к курсу и ваши результаты сохранятся.</p><a class="btn" href="{{ route('portal.password.request') }}">Получить новую ссылку</a>
@elseif($mode === 'request')
<p>Укажите почту, с которой записывались на курс. Для первого входа и восстановления мы отправим персональную ссылку.</p>
<form method="post" action="{{ route('portal.password.email') }}">@csrf<div class="form-grid"><label class="field full">Электронная почта<input name="email" type="email" required autocomplete="email" value="{{ old('email') }}"></label><button class="btn full">Отправить ссылку</button></div></form>
@else
<p>Не менее 8 символов, включая буквы и цифры. Пароль не отправляется по почте.</p>
<form method="post" action="{{ route('portal.password.save', $token) }}">@csrf<div class="form-grid"><label class="field full">Пароль<input type="password" name="password" autocomplete="new-password" minlength="8" required></label><label class="field full">Повторите пароль<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label><button class="btn full">Сохранить и открыть ЛК</button></div></form>
@endif
<p><a class="text-link" href="{{ route('login') }}">Вернуться ко входу</a></p></div></section></main>
@endsection
