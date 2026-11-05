<x-filament-panels::page>
@if($person)
<x-filament::button wire:click="selectStudent(null)" color="gray">← Все слушатели</x-filament::button>
<x-filament::section :heading="$person->name">
<p>{{ $person->email }}</p>
<h3 style="margin-top:16px">Группы и доступ</h3>
@forelse($person->enrollments as $enrollment)
<p><a href="{{ \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl('view', ['record' => $enrollment->group]) }}">{{ $enrollment->group->title }} · {{ $enrollment->group->code }}</a> — {{ $enrollment->group->availableTo($person) ? 'Доступ открыт' : 'Доступ завершён' }}</p>
@empty<p>Доступ к группам ещё не открыт.</p>@endforelse
</x-filament::section>
<h2>История посещений в ЛК</h2>
@endif
<p>Открытия и обновления страниц ЛК · время Екатеринбурга (UTC+5). Посещения учитываются и при сохранённой авторизации.</p>
{{ $this->table }}
</x-filament-panels::page>
