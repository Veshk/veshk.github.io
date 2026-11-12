@php
    $tabs = [
        ['Обзор', \App\Filament\Pages\LearningManagement::getUrl()],
        ['Заявки', \App\Filament\Resources\EnrollmentApplications\EnrollmentApplicationResource::getUrl()],
        ['Группы', \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl()],
        ['Расписание', \App\Filament\Pages\Schedules::getUrl()],
        ['Программы', \App\Filament\Resources\Programs\ProgramResource::getUrl()],
        ['Бесплатные курсы', \App\Filament\Pages\CourseCatalog::getUrl(['kind' => 'free'])],
        ['Курсы в записи', \App\Filament\Pages\CourseCatalog::getUrl(['kind' => 'recorded'])],
        ['Заказы курсов', \App\Filament\Pages\CourseOrders::getUrl()],
        ['Слушатели', \App\Filament\Pages\Students::getUrl()],
        ['Научные руководители', \App\Filament\Resources\AcademicAdvisors\AcademicAdvisorResource::getUrl()],
        ['Администраторы', \App\Filament\Pages\StaffProfiles::getUrl()],
    ];
@endphp
<nav class="portal-workspace-tabs" aria-label="Разделы управления" x-data>
    @foreach($tabs as [$label, $url])
        <a href="{{ $url }}" :aria-current="window.location.pathname.startsWith(new URL($el.href).pathname) && (!new URL($el.href).searchParams.has('kind') || (new URL(window.location.href).searchParams.get('kind') || 'free') === new URL($el.href).searchParams.get('kind')) ? 'page' : null">{{ $label }}</a>
    @endforeach
</nav>
