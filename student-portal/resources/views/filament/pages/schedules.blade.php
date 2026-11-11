<x-filament-panels::page>
    <p class="portal-intro">Расписание всех групп. Выберите группу, чтобы посмотреть занятия и массово обновить их таблицей.</p>
    <div class="portal-filters">
        <label class="portal-field">Поиск группы<input wire:model.live.debounce.300ms="search" type="search" placeholder="Название или ТЕГ"></label>
        <label class="portal-field">Администратор<select wire:model.live="owner"><option value="">Все администраторы</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
        <label class="portal-check"><input wire:model.live="onlyMine" type="checkbox"> Только мои группы</label>
    </div>
    <div class="portal-table-scroll"><table class="portal-table portal-table--groups"><thead><tr><th>Группа</th><th>Администратор</th><th>Занятий в ЛК</th><th>Действия</th></tr></thead><tbody>
    @forelse($groups as $item)
        @php($schedules = collect(\App\Support\CoursePage::forGroup($item))->where('type', 'schedule'))
        <tr><td><button wire:click="selectGroup({{ $item->id }})" class="portal-group-link">{{ $item->title }}</button><small>{{ $item->code }}</small></td><td data-label="Администратор">{{ $item->administrator?->name }}</td><td data-label="Занятий в ЛК"><span class="portal-count">{{ $schedules->filter(fn($block) => ($block['data']['visible'] ?? true) !== false)->sum(fn($block) => count($block['data']['lessons'] ?? [])) }}</span></td><td class="portal-table-actions"><x-filament::button wire:click="selectGroup({{ $item->id }})" size="sm">{{ auth()->user()->can('update', $item) ? 'Посмотреть и править' : 'Только просмотр' }}</x-filament::button></td></tr>
    @empty<tr><td colspan="4" class="portal-empty">Группы не найдены.</td></tr>@endforelse
    </tbody></table></div>
    {{ $groups->links() }}
    @if($group)
        <section class="portal-workspace" wire:key="schedule-group-{{ $group->id }}">
            <h2>{{ $group->title }} / {{ $group->code }}</h2>
            <a href="{{ route('student.group', $group) }}" target="_blank" rel="noopener" class="portal-link">Открыть ЛК группы ↗</a>
            @foreach($blocks as $block)
                <details open><summary>{{ $block['data']['title'] ?? 'Расписание' }} — {{ count($block['data']['lessons'] ?? []) }} занятий{{ ($block['data']['visible'] ?? true) === false ? ' · Скрыто в ЛК' : '' }}</summary>
                    <div class="portal-table-scroll" style="max-height:440px"><table class="portal-table"><thead><tr>@foreach(['Дата','Время ЕКБ','Тема','Преподаватель','Часы'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse(collect($block['data']['lessons'] ?? [])->sortBy(fn($lesson) => ($lesson['date'] ?? '').($lesson['time'] ?? '')) as $lesson)<tr>@foreach(['date','time','title','teacher','hours'] as $field)<td>{{ $lesson[$field] ?? '' }}</td>@endforeach</tr>@empty<tr><td colspan="5" class="portal-empty">Расписание ещё не опубликовано.</td></tr>@endforelse</tbody></table></div>
                </details>
            @endforeach
            @if(auth()->user()->can('update', $group))
                @if($blocks)<x-filament::button wire:click="editTable" color="gray">Загрузить выбранный блок в таблицу для правки</x-filament::button>@endif
                @include('filament.pages.schedule-import-form')
            @else<p>Только просмотр. Изменять расписание может администратор группы или старший администратор.</p>@endif
        </section>
    @endif
</x-filament-panels::page>
