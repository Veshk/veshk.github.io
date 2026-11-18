<x-filament-panels::page>
@if($group)
<x-filament::button tag="a" :href="\App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl()" color="gray">← К группам</x-filament::button>
<x-filament::section :heading="'Участники группы «'.$group->title.'»'">
<p>{{ $group->code }} Зачислено: <strong>{{ $participantCount }}</strong> Ожидают одобрения: <strong>{{ $pendingCount }}</strong></p>
<p class="portal-intro">В списке зачисленные слушатели и ожидающие одобрения. Заявка сама по себе не открывает доступ к обучению.</p>
@if($pendingCount)
<x-filament::button tag="a" :href="$groupApplicationsUrl" color="warning">Рассмотреть заявки ({{ $pendingCount }})</x-filament::button>
@endif
</x-filament::section>
@endif
<p class="portal-intro">Открытия и обновления страниц ЛК время Екатеринбурга (UTC+5).</p>
{{ $this->table }}
<x-filament::modal id="student-history" heading="Слушатель и история посещений" width="3xl" sticky-header sticky-footer>
@if($person)
<div class="portal-student-history" x-data x-init="$nextTick(() => $dispatch('open-modal', {id: 'student-history'}))">
<h2 class="portal-profile-heading">{{ $person->name }}</h2>
<p>{{ $person->email }}</p>
<x-filament::button tag="a" :href="route('student.home', ['as_student' => $person->id])" target="_blank" color="gray" icon="heroicon-o-eye">Открыть ЛК слушателя</x-filament::button>
@if($person->pendingApplications->isNotEmpty())
<h3>Ожидающие заявки</h3>
@foreach($person->pendingApplications as $application)
<p>{{ $application->group?->title ?? 'Группа не выбрана' }} {{ $application->group?->code }}<br>
<x-filament::badge color="warning">Ожидает одобрения</x-filament::badge>
<a class="portal-link" href="{{ $this->applicationListUrl($person, $application->study_group_id) }}">Перейти к заявке →</a></p>
@endforeach
@endif
<h3>Программы обучения</h3>
@forelse($person->enrollments as $enrollment)
<p><a class="portal-link" href="{{ \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl('view', ['record' => $enrollment->group]) }}">{{ $enrollment->group->title }} {{ $enrollment->group->code }}</a> — {{ $enrollment->group->availableTo($person) ? 'Доступ открыт' : 'Доступ завершён' }}</p>
@empty<p>Доступ к группам ещё не открыт.</p>@endforelse
<h3>Бесплатные курсы и курсы в записи</h3>
@forelse($person->selfStudyEnrollments as $access)
<p><strong>{{ $access->course->title }}</strong><br>{{ $access->course->kindLabel() }}<br>{{ $access->statusLabel() }} до {{ $access->expires_at->timezone('Asia/Yekaterinburg')->format('d.m.Y H:i') }}<br>Прогресс {{ $access->progress() }}%
@if($access->course->canManage(auth()->user()))<br><a class="portal-link" href="{{ \App\Filament\Pages\CourseAccesses::getUrl(['course' => $access->self_study_course_id]) }}">Управление доступом</a>@endif</p>
@empty<p>Зачислений на курсы пока нет</p>@endforelse
@if($person->courseOrders->isNotEmpty())
<h3>Заказы курсов в записи</h3>
@foreach($person->courseOrders as $order)<p>{{ $order->course->title }} — {{ $order->statusLabel() }}</p>@endforeach
@endif
<h3>История посещений Екатеринбург</h3>
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
