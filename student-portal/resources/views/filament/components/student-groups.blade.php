@php($student = $getRecord())
<div class="px-3 py-2 space-y-2">
    @foreach($student->enrollments as $enrollment)
        <div><a class="portal-link" href="{{ \App\Filament\Pages\Students::getUrl(['group' => $enrollment->study_group_id]) }}">{{ $enrollment->group->code }}</a><div class="text-xs text-gray-500">Зачислен</div></div>
    @endforeach
    @foreach($student->pendingApplications as $application)
        <div>
            @if($application->group)
                <a class="portal-link" href="{{ \App\Filament\Pages\Students::getUrl(['group' => $application->study_group_id]) }}">{{ $application->group->code }}</a>
            @else
                <span>Группа не выбрана</span>
            @endif
            <x-filament::badge color="warning">Ожидает одобрения</x-filament::badge>
        </div>
    @endforeach
    @if($student->enrollments->isEmpty() && $student->pendingApplications->isEmpty())
        <span class="text-gray-500">Нет доступа к группам</span>
    @endif
</div>
