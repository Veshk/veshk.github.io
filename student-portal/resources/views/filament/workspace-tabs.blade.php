@php
    $tabs = [
        ['Заявки', \App\Filament\Resources\EnrollmentApplications\EnrollmentApplicationResource::getUrl()],
        ['Группы', \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl()],
        ['Расписание', \App\Filament\Pages\Schedules::getUrl()],
        ['Программы', \App\Filament\Resources\Programs\ProgramResource::getUrl()],
        ['Слушатели', \App\Filament\Pages\Students::getUrl()],
        ['Научные руководители', \App\Filament\Resources\AcademicAdvisors\AcademicAdvisorResource::getUrl()],
        ['Администраторы', \App\Filament\Pages\StaffProfiles::getUrl()],
    ];
@endphp
<nav class="portal-workspace-tabs" aria-label="Разделы управления" x-data>
    @foreach($tabs as [$label, $url])
        <a href="{{ $url }}" :aria-current="window.location.pathname.startsWith(new URL($el.href).pathname) ? 'page' : null">{{ $label }}</a>
    @endforeach
</nav>
