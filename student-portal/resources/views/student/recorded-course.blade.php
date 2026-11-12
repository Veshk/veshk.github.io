@php
    $lessons = $course->lessons;
    $completed = $enrollment?->completed_lessons ?? [];
    $lessonIndex = $lesson ? $lessons->search(fn ($item) => $item->id === $lesson->id) : 0;
    $moduleTitle = fn ($item) => $item->module_title ?: preg_replace('/ — часть \d+$/u', '', $item->title);
    $modules = $lessons->chunkWhile(fn ($item, $key, $chunk) => $moduleTitle($item) === $moduleTitle($chunk->last()));
    $minutes = $lessons->sum('duration_minutes');
    $returnUrl = auth()->user()->isStaff() ? \App\Filament\Pages\CourseCatalog::getUrl(['kind' => $course->kind]) : route('student.home', ['list' => 1]);
@endphp
<link rel="stylesheet" href="{{ asset('self-study/course.css') }}?v=20261112-1">
<link rel="stylesheet" href="{{ asset('self-study/recorded-course.css') }}?v=20261112-2">
<main class="shell rc-course">
    <div class="rc-breadcrumb"><a href="{{ $returnUrl }}">← {{ auth()->user()->isStaff() ? 'К управлению курсами' : 'Моё обучение' }}</a><span>{{ $course->title }}</span></div>
    @if(! $enrollment)<div class="rc-preview">Предпросмотр администратора — прогресс слушателей не меняется</div>@endif
    <section class="rc-hero">
        <img src="{{ $course->coverUrl() }}" alt="">
        <div class="rc-hero-content"><p class="eyebrow">Курс в записи</p><h1>{{ $course->title }}</h1>
            <div class="rc-facts"><div><span>Эксперт курса</span><strong>{{ $course->expert }}</strong></div><div><span>Программа</span><strong>{{ $lessons->count() }} уроков</strong></div><div><span>Время видео</span><strong>@if($minutes >= 60){{ intdiv($minutes, 60) }} ч @endif{{ $minutes % 60 }} мин</strong></div></div>
        </div>
    </section>
    <div class="rc-status"><div><p class="eyebrow">{{ $enrollment ? 'Ваш прогресс' : 'В удобном темпе' }}</p>
        @if($enrollment)
            <div style="display:flex;justify-content:space-between;gap:20px;font-size:12px"><span><span data-rc-completed>{{ $lessons->whereIn('id', $completed)->count() }}</span> из {{ $lessons->count() }} уроков</span><span data-progress>{{ $enrollment->progress() }}%</span></div>
            <div class="progress" data-rc-meter role="progressbar" aria-label="Пройденные уроки" aria-valuenow="{{ $enrollment->progress() }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $enrollment->progress() }}%"></span></div>
        @else<p>Смотрите уроки и возвращайтесь к материалам</p>@endif
    </div><div class="rc-access"><span class="rc-access-label">{{ $enrollment ? 'Доступ открыт' : 'Доступ к курсу' }}</span><strong>{{ $enrollment ? 'До '.$enrollment->expires_at->timezone('Asia/Yekaterinburg')->format('d.m.Y') : $course->access_days.' дней после оплаты' }}</strong><span>У каждого курса свой срок</span></div></div>
    @include('partials.errors')
    @if($lesson)<nav class="rc-nav" aria-label="Разделы курса"><a href="#rc-learning">Обучение</a><a href="#rc-materials">Материалы урока</a><a href="#rc-notes">Мои заметки</a><a href="#rc-help">Помощь</a></nav>@endif
    @if($course->description)<details class="rc-panel" style="margin-bottom:24px"><summary>О курсе</summary><p class="rc-description">{{ $course->description }}</p></details>@endif
    @if($lesson)
    <div class="rc-layout" id="rc-learning">
        <aside class="rc-sidebar"><details class="rc-program" open><summary>Программа курса <span>{{ $lessons->count() }} уроков ⌄</span></summary><nav class="rc-program-body" aria-label="Уроки курса">
            @foreach($modules as $module)
                <details class="rc-module" {{ $module->contains('id', $lesson->id) ? 'open' : '' }}>
                    <summary><span class="rc-module-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $moduleTitle($module->first()) }}<small data-module-progress>{{ $module->whereIn('id', $completed)->count() }} из {{ $module->count() }} пройдено</small></span><span class="rc-chevron" aria-hidden="true">⌄</span></summary>
                    <ol>@foreach($module as $item)
                        @php($number = $lessons->search(fn ($entry) => $entry->id === $item->id) + 1)
                        <li><a href="{{ route('student.lesson', [$course, $item]) }}#rc-learning" @if($item->id === $lesson->id)aria-current="page"@endif><span class="rc-lesson-number" data-lesson-check="{{ $item->id }}" data-completed="{{ in_array($item->id, $completed) ? 'true' : 'false' }}" data-number="{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}">{{ in_array($item->id, $completed) ? '✓' : str_pad($number, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $item->title }}<small>{{ $item->duration_minutes }} мин</small></span>@if($item->id === $lesson->id)<span class="rc-current">Вы здесь</span>@endif</a></li>
                    @endforeach</ol>
                </details>
            @endforeach
        </nav></details></aside>
        <div class="rc-workspace">
            <section class="rc-panel rc-lesson"><div class="rc-lesson-meta"><span class="eyebrow">Урок {{ $lessonIndex + 1 }} из {{ $lessons->count() }}</span><span>{{ $lesson->duration_minutes }} мин</span></div><h2>{{ $lesson->title }}</h2>
                @if(! $lesson->is_published)<p class="rc-notice">Черновик урока — пока не виден слушателям</p>@endif
                @if($lesson->embedUrl())
                    <div class="self-study-video"><iframe title="{{ $lesson->title }}" data-kinescope-url="{{ $lesson->videoUrl() }}" data-embed-src="{{ $lesson->embedUrl() }}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen></iframe><span data-player-status>Загружаем плеер</span></div>
                    <noscript><p><a href="{{ $lesson->videoUrl() }}" target="_blank" rel="noopener">Открыть видео ↗</a></p></noscript>
                @elseif($lesson->videoUrl())
                    <div class="rc-video-empty"><span class="rc-video-symbol" aria-hidden="true">▷</span><strong>Запись урока</strong><p>Видео откроется в отдельном окне</p><a class="btn" style="margin-top:20px" href="{{ $lesson->videoUrl() }}" target="_blank" rel="noopener">Смотреть видео ↗</a></div>
                @else
                    <div class="rc-video-empty"><span class="rc-video-symbol" aria-hidden="true">▷</span><strong>Запись готовится</strong><p>Видео появится здесь после публикации</p></div>
                @endif
                @if($lesson->description)<p class="rc-description">{{ $lesson->description }}</p>@endif
                @if($enrollment)
                    <form method="post" action="{{ route('student.lesson.progress', [$course, $lesson]) }}" data-progress-form data-lesson-id="{{ $lesson->id }}">@csrf<input type="hidden" name="completed" value="{{ in_array($lesson->id, $completed) ? 0 : 1 }}"><div class="rc-completion"><button class="btn secondary" data-complete-button>{{ in_array($lesson->id, $completed) ? 'Снять отметку о прохождении' : 'Отметить пройденным' }}</button><p class="muted" data-playback-hint>{{ $lesson->embedUrl() ? 'Урок отметится автоматически, когда начнётся видео. Отметку можно изменить вручную.' : 'После изучения отметьте урок как пройденный' }}</p></div><p class="muted" role="status" data-progress-status></p></form>
                @else<p class="muted rc-completion" data-playback-hint>Предпросмотр не меняет прогресс слушателей</p>@endif
                <div class="rc-lesson-navigation">
                    @if($lessonIndex > 0)<a class="rc-previous" href="{{ route('student.lesson', [$course, $lessons[$lessonIndex - 1]]) }}#rc-learning">← Предыдущий урок</a>@else<span></span>@endif
                    @if($lessonIndex < $lessons->count() - 1)<a class="btn" href="{{ route('student.lesson', [$course, $lessons[$lessonIndex + 1]]) }}#rc-learning">Следующий урок →</a>@else<a class="btn" href="{{ $returnUrl }}">{{ $enrollment ? 'К моему обучению' : 'К управлению курсами' }} →</a>@endif
                </div>
            </section>
            <section class="rc-panel" id="rc-materials"><p class="eyebrow">К этому уроку</p><h2>Материалы</h2>
                @forelse($lesson->materials ?? [] as $index => $material)
                    <a class="rc-resource" href="{{ route('student.lesson.material', [$course, $lesson, $index]) }}"><span>{{ $material['title'] ?? 'Материал' }}</span><span aria-hidden="true">↓</span></a>
                @empty<div class="rc-empty"><span aria-hidden="true">↓</span><div><strong>Дополнительных файлов пока нет</strong><p>Если к уроку появятся материалы, они будут доступны в этом разделе</p></div></div>@endforelse
            </section>
            <section class="rc-panel" id="rc-notes"><p class="eyebrow">Сохраните главное</p><h2>Мои заметки</h2><p class="muted rc-intro">Личные заметки сохраняются отдельно для каждого урока</p>
                @if($enrollment)<form method="post" action="{{ route('student.lesson.note', [$course, $lesson]) }}">@csrf<label class="rc-field" for="lesson-note">Что хочу применить в работе<textarea id="lesson-note" name="note" rows="5" maxlength="5000" placeholder="Главная мысль, полезный пример или следующий шаг">{{ old('note', $enrollment->notes[$lesson->id] ?? '') }}</textarea></label><div class="rc-note-actions"><button class="btn secondary">Сохранить заметку</button><span class="muted">Видно только вам</span></div></form>
                @else<label class="rc-field">Что хочу применить в работе<textarea rows="5" disabled placeholder="Главная мысль, полезный пример или следующий шаг"></textarea></label><div class="rc-note-actions"><button class="btn secondary" disabled>Сохранить заметку</button><span class="muted">Доступно в режиме слушателя</span></div>@endif
            </section>
            <section class="rc-panel rc-help" id="rc-help"><div><p class="eyebrow">Мы рядом</p><h2>Нужна помощь?</h2><p>С вопросами по доступу, видео и продлению поможет команда ВЭШ</p></div><a class="btn secondary" href="mailto:info@veshk.ru">Написать в школу ↗</a></section>
        </div>
    </div>
    @else<section class="rc-panel"><h2>Курс готовится</h2><p>Опубликованных уроков пока нет</p></section>@endif
</main>
<script type="module" src="{{ asset('self-study/course.mjs') }}?v=20261112-2"></script>
