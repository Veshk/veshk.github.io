<x-filament-panels::page>
@if($group)
<x-filament::button tag="a" :href="\App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl()" color="gray">← К группам</x-filament::button>
<x-filament::section :heading="'Участники группы «'.$group->title.'»'">
<p>{{ $group->code }} · Всего участников: <strong>{{ $participantCount }}</strong></p>
<p class="portal-intro">Зачисленные слушатели, включая тех, чей доступ завершён. Заявки на проверке сюда не входят.</p>
</x-filament::section>
@endif
<p class="portal-intro">Открытия и обновления страниц ЛК · время Екатеринбурга (UTC+5).</p>
{{ $this->table }}
<x-filament::modal id="student-history" heading="Слушатель и история посещений" width="3xl" sticky-header sticky-footer>
@if($person)
<div class="portal-student-history" x-data x-init="$nextTick(() => $dispatch('open-modal', {id: 'student-history'}))">
<h2 class="portal-profile-heading">{{ $person->name }}</h2>
<p>{{ $person->email }}</p>
<h3>Группы и доступ</h3>
@forelse($person->enrollments as $enrollment)
<p><a class="portal-link" href="{{ \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl('view', ['record' => $enrollment->group]) }}">{{ $enrollment->group->title }} · {{ $enrollment->group->code }}</a> — {{ $enrollment->group->availableTo($person) ? 'Доступ открыт' : 'Доступ завершён' }}</p>
@empty<p>Доступ к группам ещё не открыт.</p>@endforelse
<h3>История посещений · Екатеринбург</h3>
<div class="portal-table-scroll"><table class="portal-table">
<thead><tr><th>Дата и время</th><th>Открытая страница</th></tr></thead>
<tbody>@forelse($visits as $visit)<tr><td>{{ $visit->visited_at->timezone('Asia/Yekaterinburg')->format('d.m.Y H:i:s') }}</td><td>{{ $visit->page }}</td></tr>
@empty<tr><td colspan="2" class="portal-empty">Посещений пока нет.</td></tr>@endforelse</tbody>
</table></div>
@if($visits->hasPages())
<div class="portal-history-pagination">
<x-filament::button color="gray" wire:click="changeHistoryPage({{ $visits->currentPage() - 1 }})" :disabled="$visits->onFirstPage()">Назад</x-filament::button>
<span>{{ $visits->currentPage() }} / {{ $visits->lastPage() }}</span>
<x-filament::button color="gray" wire:click="changeHistoryPage({{ $visits->currentPage() + 1 }})" :disabled="!$visits->hasMorePages()">Далее</x-filament::button>
</div>
@endif
</div>
@endif
<x-slot name="footer"><x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'student-history'})">Закрыть</x-filament::button></x-slot>
</x-filament::modal>
</x-filament-panels::page>
