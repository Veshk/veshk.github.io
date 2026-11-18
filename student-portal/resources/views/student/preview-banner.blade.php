<div class="student-preview-banner" role="region" aria-label="Просмотр как студент">
    <div><strong>Вы смотрите как студент</strong><span>{{ $previewStudent?->name ?? 'Общий вид обучения' }}</span><small>Режим просмотра — прогресс, ответы и посещения слушателя не меняются</small></div>
    <a class="btn outline" href="{{ $previewStudent ? \App\Filament\Pages\Students::getUrl(['student' => $previewStudent->id]) : \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl() }}">Вернуться в управление</a>
</div>
<script>(()=>{const banner=document.querySelector('.student-preview-banner');new ResizeObserver(()=>document.documentElement.style.setProperty('--student-preview-height',banner.offsetHeight+'px')).observe(banner);})();</script>
