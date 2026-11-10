@php
    $safe = fn ($url) => \App\Support\CoursePage::safeUrl($url);
@endphp
@if(in_array($type, ['introductions', 'video'], true))
@php
    $isVideo = $type === 'video';
    $title = ($data['title'] ?? '') ?: \App\Support\CoursePage::BLOCKS[$type];
    $actionUrl = $safe($data['url'] ?? null);
    $imageUrl = $safe($data['image_url'] ?? null);
    $actionLabel = ($data['button'] ?? '') ?: ($isVideo ? 'Смотреть видео' : 'Перейти в Holst');
@endphp
<div class="welcome-card {{ $isVideo ? 'welcome-video' : 'welcome-intro' }}">
    <div class="welcome-copy"><p class="welcome-handwriting">{{ $isVideo ? 'Это ВЭШ' : 'Давайте знакомиться!' }}</p><h2>{{ $title }}</h2><p class="block-text">{{ ($data['text'] ?? '') ?: ($isVideo ? 'Познакомьтесь со школой в коротком видео.' : 'Расскажите о себе и познакомьтесь с коллегами по обучению.') }}</p>@if($actionUrl)<a class="btn welcome-action" href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer">{{ $actionLabel }} ↗</a>@endif</div>
    @if($isVideo && $actionUrl)<a class="welcome-art" href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $actionLabel }}: {{ $title }}">@else<div class="welcome-art">@endif
        @if($imageUrl)<img class="block-image" src="{{ $imageUrl }}" alt="{{ $data['image_alt'] ?? '' }}" loading="lazy" referrerpolicy="no-referrer">@endif
        @if($isVideo)<span class="welcome-play" aria-hidden="true">▶</span><span class="welcome-video-label" aria-hidden="true">ВИДЕО О ШКОЛЕ</span>@else<span class="welcome-hello" aria-hidden="true">Привет!</span>@endif
    @if($isVideo && $actionUrl)</a>@else</div>@endif
</div>
@elseif($type === 'contacts')
@php
    $contactLines = \App\Support\CoursePage::contactLines(($data['text'] ?? '') ?: ($group->administrator->contact_text ?: 'Поможет с расписанием и организационными вопросами.'), $group->administrator->name);
    $chatText = $data['chat_text'] ?? 'Присоединяйтесь к чатам, чтобы быть на связи с группой и получать объявления.';
    $chats = array_values(array_filter(!empty($data['chats']) ? $data['chats'] : ($group->chat_url ? [['label' => 'Чат группы', 'url' => $group->chat_url]] : []), fn ($chat) => $safe($chat['url'] ?? null)));
@endphp
<div class="contact-panel">
    <div class="contact-person">
        @if($group->administrator->profile_photo)<img class="staff-photo" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($group->administrator->profile_photo) }}" alt="Фото администратора">@else<span class="avatar staff-photo">{{ mb_substr($group->administrator->name,0,1) }}</span>@endif
        <div class="contact-details">
            <div class="eyebrow muted">Администратор группы</div><h3>{{ $group->administrator->name }}</h3>
            <div class="contact-info">@foreach($contactLines as $line)<p class="contact-line{{ $line['linked'] ? '' : ' contact-note' }}">@foreach($line['parts'] as $part)@if($part['href'])<a href="{{ $part['href'] }}">{{ $part['text'] }}</a>@else{{ $part['text'] }}@endif@endforeach</p>@endforeach</div>
            @if($url = $safe(($data['contact_url'] ?? '') ?: $group->administrator->contact_url))<a class="contact-message" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Написать администратору ↗</a>@endif
        </div>
    </div>
    <div class="contact-chats"><h3>Чаты группы</h3>@if($chatText)<p class="chat-description">{{ $chatText }}</p>@endif<div class="chat-links">@forelse($chats as $chat)<a class="btn outline" href="{{ $safe($chat['url']) }}" target="_blank" rel="noopener noreferrer">{{ $chat['label'] }} ↗</a>@empty<p class="muted">Ссылки на чаты скоро появятся.</p>@endforelse</div></div>
</div>
@elseif($type === 'schedule')
@php
    $months = collect($data['lessons'] ?? [])->filter(fn ($l) => !empty($l['date']))->sortBy(fn ($l) => $l['date'].' '.($l['time'] ?? ''))->groupBy(fn ($l) => substr($l['date'], 0, 7));
    $today = now('Asia/Yekaterinburg')->toDateString();
@endphp
@forelse($months as $month => $lessons)
@php
    $past = ($data['collapse_past'] ?? false) && $lessons->every(fn ($l) => $l['date'] < $today);
@endphp
@if($past)<details class="past-schedule"><summary>Прошедшие занятия — {{ \Carbon\Carbon::parse($month.'-01')->locale('ru')->translatedFormat('F Y') }}</summary>@endif
<div class="month-card"><div class="month-label">{{ \Carbon\Carbon::parse($month.'-01')->locale('ru')->translatedFormat('F Y') }}</div><div class="schedule-scroll"><table class="compact-schedule"><colgroup><col class="col-date"><col class="col-time"><col class="col-lesson"><col class="col-teacher"></colgroup><thead><tr><th>Дата</th><th>Время ЕКБ{{ ($data['show_moscow'] ?? false) ? ' / МСК' : '' }}</th><th>Занятие</th><th>Преподаватель</th></tr></thead><tbody>
@foreach($lessons as $lesson)<tr><td><strong>{{ \Carbon\Carbon::parse($lesson['date'])->format('d.m.Y') }}</strong> <small>{{ \Carbon\Carbon::parse($lesson['date'])->locale('ru')->translatedFormat('D') }}</small></td><td class="lesson-time">{{ $lesson['time'] ?? '' }}<span class="lesson-timezone"> ЕКБ</span>@if($data['show_moscow'] ?? false)<small>{{ \App\Support\CoursePage::moscow($lesson['time'] ?? '') }} МСК</small>@endif</td><td><strong>{{ $lesson['title'] ?? '' }}</strong><div class="lesson-context"><span>{{ $lesson['module'] ?? '' }}</span><span>{{ $lesson['format'] ?? $group->format }}</span></div>@if($url = $safe($lesson['url'] ?? null))<a class="text-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Подключиться ↗</a>@endif</td><td>{{ $lesson['teacher'] ?? '' }}</td></tr>@endforeach
</tbody></table></div></div>@if($past)</details>@endif
@empty<div class="empty">Расписание ещё не опубликовано.</div>@endforelse
@if($url = $safe($data['source_url'] ?? null))<a class="btn outline" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Открыть исходное расписание ↗</a>@endif
@elseif($type === 'homework')
@if(!empty($data['tasks']))
<div class="homework-scroll"><table class="homework-table"><thead><tr><th>Статус</th><th>Срок</th><th>Модуль и задание</th><th>Материалы</th></tr></thead><tbody>
@foreach($data['tasks'] as $task)<tr><td><span class="content-status {{ ($task['status'] ?? '') === 'past' ? 'is-past' : 'is-upcoming' }}">{{ ($task['status'] ?? '') === 'past' ? 'Прошедшее' : 'Предстоит' }}</span></td><td class="homework-deadline">{{ !empty($task['deadline']) ? \Carbon\Carbon::parse($task['deadline'])->format('d.m.Y') : 'Уточняется' }}</td><td>@if(!empty($task['module']))<div class="homework-module">{{ $task['module'] }}</div>@endif<p class="block-text">{{ $task['title'] ?? '' }}</p></td><td>@if($url = $safe($task['url'] ?? null))<a class="text-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ($task['button'] ?? '') ?: 'Открыть' }} ↗</a>@endif</td></tr>@endforeach
</tbody></table></div><p class="form-note">Статус общий для группы и не обозначает личное выполнение задания.</p>
@else<div class="empty">Задания появятся здесь после публикации.</div>@endif
@elseif($type === 'faq')
@forelse($data['questions'] ?? [] as $question)<details class="course-faq"><summary>{{ $question['question'] ?? '' }}</summary><div class="faq-answer"><p class="block-text">{{ $question['answer'] ?? '' }}</p>@if($url = $safe($question['url'] ?? null))<a class="text-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ($question['button'] ?? '') ?: 'Подробнее' }} ↗</a>@endif</div></details>@empty<div class="empty">Ответы на вопросы скоро появятся.</div>@endforelse
@elseif($type === 'recordings')
@forelse(collect($data['videos'] ?? [])->groupBy(fn ($v) => ($v['module'] ?? '') ?: 'Записи занятий') as $module => $videos)
<div class="recording-module"><h3>{{ $module }}</h3><div class="recording-grid">@foreach($videos as $video)
@php
    $videoUrl = $safe($video['url'] ?? null);
    $ready = (($video['status'] ?? '') ?: ($videoUrl ? 'published' : 'pending')) === 'published' && $videoUrl;
@endphp
<article class="card recording-card {{ $ready ? '' : 'is-pending' }}"><span class="content-status {{ $ready ? 'is-published' : 'is-upcoming' }}">{{ $ready ? 'Запись опубликована' : 'Видео ещё не опубликовано' }}</span>@if(!empty($video['date']))<time datetime="{{ $video['date'] }}">{{ \Carbon\Carbon::parse($video['date'])->format('d.m.Y') }}</time>@endif<h3>{{ $video['title'] }}</h3>@if(!empty($video['text']))<p class="block-text">{{ $video['text'] }}</p>@endif @if($ready)<a class="btn outline" href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer">Смотреть запись ↗</a>@else<p class="muted">Ссылка появится после публикации видео.</p>@endif</article>
@endforeach</div></div>@empty<div class="empty">Записи появятся здесь после занятий.</div>@endforelse
@elseif($type === 'materials')
@if(!empty($data['resources']))
@if(!empty($data['text']))<p class="block-text">{{ $data['text'] }}</p>@endif
@php
    $resources = array_merge(!empty($data['url']) ? [['title' => 'Папка вашей группы', 'url' => $data['url']]] : [], $data['resources']);
@endphp
<div class="resource-grid">@foreach($resources as $resource)<article class="card resource-card"><h3>{{ $resource['title'] ?? '' }}</h3>@if(!empty($resource['text']))<p class="block-text">{{ $resource['text'] }}</p>@endif @if($url = $safe($resource['url'] ?? null))<a class="btn outline" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ($resource['button'] ?? '') ?: 'Открыть материалы' }} ↗</a>@endif</article>@endforeach</div>
@else
<div class="folder-banner"><svg width="92" height="72" viewBox="0 0 92 72" aria-hidden="true"><path d="M4 14h30l9 10h45v42H4z" fill="none" stroke="currentColor" stroke-width="3"/><path d="M4 24V6h31l9 8h39v10" fill="none" stroke="currentColor" stroke-width="3"/></svg><div><h3>Папка вашей группы</h3><p>{{ $data['text'] ?? '' }}</p>@if($url = $safe($data['url'] ?? null))<a class="btn" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Перейти к материалам ↗</a>@else<p class="muted">Ссылка скоро появится.</p>@endif</div></div>
@endif
@foreach(($isAlias ?? false) ? [] : ($group->materials ?? []) as $index => $material)<a class="material" href="{{ route('student.material',[$group,$index]) }}"><span class="file-icon">TXT</span><div class="details"><h3>{{ $material['title'] }}</h3><small>Демонстрационный материал — {{ $group->code }}</small></div><span class="arrow">↓</span></a>@endforeach
@else
<div class="card information-block">@if($imageUrl = $safe($data['image_url'] ?? null))<img class="block-image" src="{{ $imageUrl }}" alt="{{ $data['image_alt'] ?? '' }}" loading="lazy" referrerpolicy="no-referrer">@endif<p class="block-text">{{ ($data['text'] ?? '') ?: ($type === 'join' ? 'Информация о подключении появится перед занятием.' : 'Администратор добавит информацию.') }}</p>@if($type === 'join' && !empty($data['platform']))<p class="muted">Платформа: {{ $data['platform'] }}</p>@endif @if($url = $safe($data['url'] ?? null))<a class="btn outline" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ($data['button'] ?? '') ?: match($type) { 'join' => 'Подключиться', 'video' => 'Смотреть видео', 'introductions' => 'Перейти в Holst', default => 'Подробнее' } }} ↗</a>@endif</div>
@endif
