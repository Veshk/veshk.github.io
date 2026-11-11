<div class="portal-application-details">
<div><strong>{{ $application->user->name }}</strong><br>{{ $application->user->email }}</div>
<dl class="portal-application-meta">
<div><dt>Группа</dt><dd>{{ $application->group?->title ?? 'Не выбрана' }}<br>{{ $application->group?->code }}</dd></div>
<div><dt>Администратор</dt><dd>{{ $application->group?->administrator?->name ?? 'Ответственные за заявки без группы' }}</dd></div>
<div><dt>Статус</dt><dd>{{ $application->statusLabel() }}</dd></div>
<div><dt>Подана</dt><dd>{{ $application->created_at->format('d.m.Y H:i') }}</dd></div>
</dl>
<div>Комментарий слушателя:<br>{{ $application->message ?: 'Не оставлен' }}</div>
@if($application->status === 'pending')
<p class="portal-intro">{{ auth()->user()->can('review', $application) ? ($application->study_group_id ? 'Проверьте группу перед открытием доступа. Если слушатель указал её неверно, измените группу.' : 'Назначьте группу слушателю. После этого можно будет открыть доступ к обучению.') : 'Только просмотр. Обработать заявку может администратор группы или старший администратор.' }}</p>
@endif
@if($application->decision_note)<div>Решение: {{ $application->decision_note }}</div>@endif
@if($application->reviewer)<div>Обработал: {{ $application->reviewer->name }}<br>{{ $application->reviewed_at?->format('d.m.Y H:i') }}</div>@endif
@if($application->status === 'approved')<div>{{ $application->send_email ? 'Письмо сохранено в тестовый перехватчик. Реальной отправки не было.' : 'Доступ открыт без письма.' }}</div>@endif
</div>
