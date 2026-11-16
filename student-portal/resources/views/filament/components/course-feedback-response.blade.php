<div class="space-y-4">
    <div><strong>{{ $enrollment->user->name }}</strong><p>{{ $enrollment->course->title }}</p></div>
    <p>Оценка курса: <strong>{{ $enrollment->feedback_rating }} из 5</strong></p>
    <div><h3>Что понравилось</h3><p class="whitespace-pre-wrap break-words">{{ $enrollment->feedback_liked ?: 'Без комментария' }}</p></div>
    <div><h3>Что не понравилось или можно улучшить</h3><p class="whitespace-pre-wrap break-words">{{ $enrollment->feedback_improvements ?: 'Без комментария' }}</p></div>
    <p>Ответ обновлён {{ $enrollment->feedback_submitted_at?->timezone('Asia/Yekaterinburg')->format('d.m.Y H:i') }}</p>
</div>
