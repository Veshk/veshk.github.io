@extends('layouts.portal')
@section('title', 'Просмотр обучения')
@section('content')
<main class="shell">
    <div class="page-heading"><div><div class="eyebrow muted">Режим просмотра</div><h1>Все группы и курсы</h1><p>Выберите обучение, чтобы увидеть его страницу со стороны слушателя</p></div></div>
    <div class="cards">
        @foreach($groups as $group)
            @php($coverUrl = $group->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($group->hero_image) : $group->program?->coverUrl())
            <article class="card course-tile"><div class="tile-cover course-hero has-group-cover"><img class="group-hero-background" src="{{ $coverUrl ?: asset($group->program?->defaultCoverPath() ?? 'brand/group-placeholder-short.png') }}" alt=""><div class="eyebrow">Программа обучения</div><h2>{{ $group->title }}</h2></div><div class="tile-bottom"><div><p>{{ $group->code }}</p><p class="muted">{{ $group->status }}</p></div><a class="btn" href="{{ \App\Http\Middleware\StudentPreview::url('student.group', $group) }}">Открыть как студент ↗</a></div></article>
        @endforeach
        @foreach($courses as $course)
            <article class="card course-tile"><div class="tile-cover course-hero has-group-cover"><img class="group-hero-background" src="{{ $course->coverUrl() }}" alt=""><div class="eyebrow">{{ $course->kindLabel() }}</div><h2>{{ $course->title }}</h2></div><div class="tile-bottom"><p>{{ $course->expert }}</p><a class="btn" href="{{ \App\Http\Middleware\StudentPreview::url('student.course', $course) }}">Открыть как студент ↗</a></div></article>
        @endforeach
    </div>
</main>
@endsection
