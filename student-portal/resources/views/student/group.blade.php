@extends('layouts.portal')
@section('title',$group->title)
@section('content')@if(auth()->user()->isStaff())<div class="admin-notice">Предпросмотр страницы слушателя — @can('update', $group)<a class="text-link" href="{{ \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl('page', ['record' => $group]) }}">Редактировать страницу ЛК →</a>@else Чужая группа: только просмотр @endcan</div>@endif
@php($coverUrl = $group->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($group->hero_image) : $group->program?->coverUrl())
<section class="course-hero has-group-cover has-learning-overview {{ $coverUrl ? '' : 'has-default-cover' }}"><img class="group-hero-background" src="{{ $coverUrl ?: asset($group->program?->defaultCoverPath() ?? 'brand/group-placeholder-short.png') }}" alt="" style="object-position:{{ in_array($group->hero_position, ['center','top','bottom'], true) ? $group->hero_position : 'center' }}"><div class="course-hero-inner"><div class="breadcrumb"><a href="{{ route('student.home',['list'=>1]) }}">Моё обучение</a><span>/</span><span>{{ $group->code }}</span></div><div class="hero-layout"><div><div class="eyebrow">Пространство обучения — {{ $group->code }}</div><h1>{{ $group->title }}</h1><div class="hero-meta"><div>Формат<strong>{{ $group->format }}</strong></div><div>Обучение<strong>{{ $group->starts_at->format('d.m.Y') }} — {{ $group->graduates_at->format('d.m.Y') }}</strong></div></div></div><div class="hero-side"><span class="badge">Доступ открыт</span>@if(filled($group->description))<p>{{ $group->description }}</p>@endif</div></div></div></section>
@php($blocks = collect(\App\Support\CoursePage::display($group))->filter(fn ($b) => isset(\App\Support\CoursePage::BLOCKS[$b['type'] ?? '']) && ($b['data']['visible'] ?? true))->values())
@include('student.learning-overview')
<nav class="course-nav" aria-label="Разделы курса"><div class="course-nav-inner">@foreach($blocks as $i => $block)<a href="#block-{{ $i }}">{{ $block['data']['title'] ?? \App\Support\CoursePage::BLOCKS[$block['type']] }}</a>@endforeach</div></nav>
<main class="shell course-content">
@php($secondInPair = false)
@foreach($blocks as $i => $block)
    @php($compact = in_array($block['type'], ['introductions', 'video', 'photos'], true))
    @php($paired = $secondInPair || ($compact && in_array($blocks->get($i + 1)['type'] ?? '', ['introductions', 'video', 'photos'], true)))
    @php($secondInPair = $paired && ! $secondInPair)
    <section class="section{{ $compact ? ' welcome-section' : '' }}{{ $paired ? ' welcome-paired' : '' }}" id="block-{{ $i }}">
        @if(! $compact)<div class="section-title"><h2>{{ $block['data']['title'] ?? \App\Support\CoursePage::BLOCKS[$block['type']] }}</h2></div>@endif
        @include('student.page-block', ['type' => $block['type'], 'data' => $block['data'], 'group' => $block['source_group_model'] ?? $group, 'isAlias' => isset($block['source_group_model'])])
    </section>
@endforeach
</main>
<script src="{{ asset('course-navigation.js') }}?v=20261111-5" defer></script>@endsection
