@extends('layouts.portal')
@section('title',$group->title)
@section('content')@if(auth()->user()->isStaff())<div class="admin-notice">Предпросмотр страницы слушателя — @can('update', $group)<a class="text-link" href="{{ \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl('page', ['record' => $group]) }}">Редактировать страницу ЛК →</a>@else Чужая группа: только просмотр @endcan</div>@endif
@include('student.group-hero')
@php($blocks = collect(\App\Support\CoursePage::visibleBlocks($group)))
@foreach($blocks as $i => $block)
    @if($block['type'] === 'preparation')<div class="shell preparation-top">@include('student.course-section', ['compact'=>false, 'paired'=>false])</div>@endif
@endforeach
@include('student.learning-overview')
<nav class="course-nav" aria-label="Разделы курса"><div class="course-nav-inner">@foreach($blocks as $i => $block)@continue(in_array($block['type'], ['introductions', 'video'], true))<a href="#block-{{ $i }}">{{ $block['data']['title'] ?? \App\Support\CoursePage::BLOCKS[$block['type']] }}</a>@endforeach</div></nav>
<main class="shell course-content">
@php($secondInPair = false)
@foreach($blocks as $i => $block)
    @php($compact = in_array($block['type'], ['introductions', 'video', 'photos'], true))
    @php($paired = $secondInPair || ($compact && in_array($blocks->get($i + 1)['type'] ?? '', ['introductions', 'video', 'photos'], true)))
    @php($secondInPair = $paired && ! $secondInPair)
    @continue($block['type'] === 'preparation')
    @include('student.course-section')
@endforeach
</main>
<script src="{{ asset('course-navigation.js') }}?v=20261112-1" defer></script>@endsection
