<x-filament-panels::page>
@if($group)
<x-filament::button tag="a" :href="\App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl()" color="gray">← К группам</x-filament::button>
<x-filament::section :heading="'Участники группы «'.$group->title.'»'">
<p>{{ $group->code }} · Всего участников: <strong>{{ $participantCount }}</strong></p>
<p>Зачисленные слушатели, включая тех, чей доступ завершён. Заявки на проверке сюда не входят.</p>
</x-filament::section>
@endif
@if($person)
<x-filament::button wire:click="selectStudent(null)" color="gray">{{ $group ? '← К участникам группы' : '← Все слушатели' }}</x-filament::button>
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
