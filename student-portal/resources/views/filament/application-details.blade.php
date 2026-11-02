<div style="display:grid;gap:16px">
<div><strong>{{ $application->user->name }}</strong><br>{{ $application->user->email }}</div>
<div>Группа: {{ $application->group?->title ?? 'Не указана' }}<br>{{ $application->group?->code }}</div>
<div>Комментарий слушателя:<br>{{ $application->message ?: 'Не оставлен' }}</div>
<div>Статус: {{ $application->statusLabel() }}</div>
@if($application->decision_note)<div>Решение: {{ $application->decision_note }}</div>@endif
@if($application->reviewer)<div>Обработал: {{ $application->reviewer->name }}<br>{{ $application->reviewed_at?->format('d.m.Y H:i') }}</div>@endif
@if($application->status === 'approved')<div>{{ $application->send_email ? 'Письмо сохранено в тестовый перехватчик. Реальной отправки не было.' : 'Доступ открыт без письма.' }}</div>@endif
</div>
