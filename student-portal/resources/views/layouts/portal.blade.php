<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>@yield('title','Личный кабинет') — ВЭШ</title><link rel="icon" href="{{ asset('brand/vesh-logo.svg') }}" type="image/svg+xml"><link rel="stylesheet" href="{{ asset('portal.css').'?v=20261112-1' }}"><link rel="stylesheet" href="{{ asset('course-page.css').'?v=20261112-1' }}"><link rel="stylesheet" href="{{ asset('portal-updates.css').'?v=20261111-11' }}"></head>
<body>
@if(! isset($authLayout))
<header class="topbar"><div class="topbar-inner"><a class="brand" href="{{ route('student.home',['list'=>1]) }}"><img class="logo" src="{{ asset('brand/vesh-logo.svg') }}" alt="Высшая экономическая школа"><span>Пространство<br>вашего обучения</span></a><nav class="top-nav"><a href="{{ route('student.home',['list'=>1]) }}">Моё обучение</a>@if(auth()->user()?->isStaff())<a class="admin-entry" href="/admin">Панель администратора</a>@endif</nav>@auth<div class="person"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span class="name">{{ auth()->user()->name }}</span></div><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Выйти ↗</button></form>@endauth</div></header>
@endif
@yield('content')
@if(! isset($authLayout))<footer class="footer"><span>© Высшая экономическая школа — Образовательные решения для бизнеса</span><a href="https://veshk.ru" target="_blank" rel="noopener">Сайт школы ↗</a></footer>@endif
@if(config('app.demo_mode'))<div class="demo-bar">Тестовый кабинет — вымышленные данные — <a class="text-link" href="{{ route('demo') }}">Выбрать демо-роль</a></div>@endif
</body></html>
