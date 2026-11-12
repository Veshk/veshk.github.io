@extends('layouts.portal')
@section('title', $course->title)
@section('content')
@if($course->kind === 'recorded')
@include('student.recorded-course')
@else
<link rel="stylesheet" href="{{ asset('self-study/course.css') }}?v=20261112-1">
<main class="shell self-study">
<a class="text-link" href="{{ auth()->user()->isStaff() ? \App\Filament\Pages\CourseCatalog::getUrl(['kind' => $course->kind]) : route('student.home', ['list' => 1]) }}">← {{ auth()->user()->isStaff() ? 'К управлению курсами' : 'Моё обучение' }}</a>
<section class="course-hero has-group-cover self-study-hero"><img class="group-hero-background" src="{{ $course->coverUrl() }}" alt=""><div class="eyebrow">{{ $course->kindLabel() }}</div><h1>{{ $course->title }}</h1><p>{{ $course->expert }}</p></section>
@include('partials.errors')
@if(! $enrollment)<p class="self-study-notice">Предпросмотр администратора. Прогресс слушателей не меняется.</p>@else<p>Доступ до {{ $enrollment->expires_at->timezone('Asia/Yekaterinburg')->format('d.m.Y') }} <span class="self-study-progress">Пройдено <strong data-progress>{{ $enrollment->progress() }}%</strong></span></p>@endif
@if($lesson)
<div class="self-study-grid">
<aside class="card self-study-menu"><h2>Уроки курса</h2><nav aria-label="Уроки курса">
@foreach($course->lessons as $item)
<a href="{{ route('student.lesson', [$course, $item]) }}" @if($item->id === $lesson->id)aria-current="page"@endif><span class="muted">{{ $loop->iteration }}</span><span>{{ $item->title }}@if($item->module_title)<small>{{ $item->module_title }}</small>@endif</span><span data-lesson-check="{{ $item->id }}">{{ in_array($item->id, $enrollment?->completed_lessons ?? []) ? '✓' : '' }}</span></a>
@endforeach
</nav></aside>
<section class="card self-study-lesson"><div class="eyebrow muted">{{ $lesson->module_title ?: 'Урок '.$course->lessons->search(fn ($item) => $item->id === $lesson->id) + 1 }}</div><h2>{{ $lesson->title }}</h2>
@if($lesson->duration_minutes)<p class="muted">{{ $lesson->duration_minutes }} мин</p>@endif
@if($lesson->embedUrl())
<div class="self-study-video"><iframe title="{{ $lesson->title }}" data-kinescope-url="{{ $lesson->videoUrl() }}" data-embed-src="{{ $lesson->embedUrl() }}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen></iframe><span data-player-status>Загружаем плеер</span></div>
<p class="muted" data-playback-hint>{{ $enrollment ? 'Урок будет отмечен как пройденный, когда начнётся воспроизведение' : 'В предпросмотре можно проверить видео урока' }}</p>
<noscript><p><a class="text-link" href="{{ $lesson->videoUrl() }}" target="_blank" rel="noopener">Открыть видео ↗</a></p></noscript>
@elseif($lesson->videoUrl())
<p><a class="btn" href="{{ $lesson->videoUrl() }}" target="_blank" rel="noopener">Смотреть видео ↗</a></p><p class="muted">После просмотра отметьте урок как пройденный</p>
@else<p class="self-study-notice">Видео готовится к публикации</p>@endif
@if($lesson->description)<p class="self-study-description">{{ $lesson->description }}</p>@endif
@if($lesson->materials)
<h3>Материалы урока</h3>
@foreach($lesson->materials as $index => $material)
<p><a class="text-link" href="{{ route('student.lesson.material', [$course, $lesson, $index]) }}">{{ $material['title'] ?? 'Материал' }} ↓</a></p>
@endforeach
@endif
@if($enrollment)
<form method="post" action="{{ route('student.lesson.progress', [$course, $lesson]) }}" data-progress-form data-lesson-id="{{ $lesson->id }}">@csrf<input type="hidden" name="completed" value="{{ in_array($lesson->id, $enrollment->completed_lessons ?? []) ? 0 : 1 }}"><button class="btn outline" data-complete-button>{{ in_array($lesson->id, $enrollment->completed_lessons ?? []) ? 'Снять отметку о прохождении' : 'Отметить пройденным' }}</button><p class="muted" role="status" data-progress-status></p></form>
<form class="self-study-note" method="post" action="{{ route('student.lesson.note', [$course, $lesson]) }}">@csrf<label for="lesson-note">Моя заметка к уроку</label><textarea id="lesson-note" name="note" rows="5" maxlength="5000">{{ old('note', $enrollment->notes[$lesson->id] ?? '') }}</textarea><button class="btn outline">Сохранить заметку</button></form>
@endif
</section></div>
@else<section class="card"><h2>Курс готовится</h2><p>Опубликованных уроков пока нет</p></section>@endif
</main><script type="module" src="{{ asset('self-study/course.mjs') }}?v=20261112-1"></script>
@endif
@endsection
