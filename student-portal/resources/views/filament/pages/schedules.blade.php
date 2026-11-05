<x-filament-panels::page>
    <p>Расписание всех групп. Выберите группу, чтобы посмотреть занятия и массово обновить их таблицей.</p>
    <div class="grid gap-4 md:grid-cols-3">
        <label>Поиск группы<input wire:model.live.debounce.300ms="search" type="search" placeholder="Название или ТЕГ" class="block w-full rounded-lg border p-3"></label>
        <label>Администратор<select wire:model.live="owner" class="block w-full rounded-lg border p-3"><option value="">Все администраторы</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
        <label><input wire:model.live="onlyMine" type="checkbox"> Только мои группы</label>
    </div>
    <div class="overflow-auto"><table class="w-full text-sm"><thead><tr><th class="p-3 text-left">Группа</th><th class="p-3 text-left">Администратор</th><th class="p-3 text-left">Занятий в ЛК</th><th class="p-3 text-left">Действия</th></tr></thead><tbody>
    @forelse($groups as $item)
        @php($schedules = collect(\App\Support\CoursePage::forGroup($item))->where('type', 'schedule'))
        <tr><td class="border-b p-3"><button wire:click="selectGroup({{ $item->id }})" class="text-left underline">{{ $item->title }}</button><small class="block">{{ $item->code }}</small></td><td class="border-b p-3">{{ $item->administrator?->name }}</td><td class="border-b p-3">{{ $schedules->filter(fn($block) => ($block['data']['visible'] ?? true) !== false)->sum(fn($block) => count($block['data']['lessons'] ?? [])) }}</td><td class="border-b p-3"><x-filament::button wire:click="selectGroup({{ $item->id }})" size="sm">{{ auth()->user()->can('update', $item) ? 'Посмотреть и править' : 'Только просмотр' }}</x-filament::button></td></tr>
    @empty<tr><td colspan="4" class="p-5">Группы не найдены.</td></tr>@endforelse
    </tbody></table></div>
    {{ $groups->links() }}
    @if($group)
        <section class="space-y-5 rounded-xl border p-5" wire:key="schedule-group-{{ $group->id }}">
            <h2 class="text-xl font-semibold">{{ $group->title }} / {{ $group->code }}</h2>
            <a href="{{ route('student.group', $group) }}" target="_blank" rel="noopener" class="underline">Открыть ЛК группы ↗</a>
            @foreach($blocks as $block)
                <details open><summary>{{ $block['data']['title'] ?? 'Расписание' }} — {{ count($block['data']['lessons'] ?? []) }} занятий{{ ($block['data']['visible'] ?? true) === false ? ' · Скрыто в ЛК' : '' }}</summary>
                    <div class="overflow-auto" style="max-height:440px"><table class="w-full text-sm"><thead><tr>@foreach(['Дата','Время ЕКБ','Тема','Преподаватель','Часы'] as $heading)<th class="border-b p-3 text-left">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse(collect($block['data']['lessons'] ?? [])->sortBy(fn($lesson) => ($lesson['date'] ?? '').($lesson['time'] ?? '')) as $lesson)<tr>@foreach(['date','time','title','teacher','hours'] as $field)<td class="border-b p-3 align-top">{{ $lesson[$field] ?? '' }}</td>@endforeach</tr>@empty<tr><td colspan="5" class="p-4">Расписание ещё не опубликовано.</td></tr>@endforelse</tbody></table></div>
                </details>
            @endforeach
            @if(auth()->user()->can('update', $group))
                @if($blocks)<x-filament::button wire:click="editTable" color="gray">Загрузить выбранный блок в таблицу для правки</x-filament::button>@endif
                @include('filament.pages.schedule-import-form')
            @else<p>Только просмотр. Изменять расписание может администратор группы или старший администратор.</p>@endif
        </section>
    @endif
</x-filament-panels::page>
