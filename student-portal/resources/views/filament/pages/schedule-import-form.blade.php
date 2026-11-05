
    <p>{{ $group->title }} / {{ $group->code }}</p>
    <p><a href="{{ $editorUrl }}" class="underline">Редактор страницы ЛК</a> / <a href="{{ route('student.group', $group) }}" target="_blank" rel="noopener" class="underline">Посмотреть расписание</a></p>
    @if(!$blocks)
        <p>Сначала добавьте блок «Расписание» в редакторе страницы.</p>
    @else
    <form wire:submit="prepare" class="space-y-5">
        <label class="block">Как обновить расписание<select wire:model.live="mergeMode" class="block w-full rounded-lg border p-3"><option value="append">Добавить новые занятия</option><option value="replace">Заменить расписание целиком</option></select></label>
        <p>{{ $mergeMode === "append" ? "Добавятся только новые строки. Существующие занятия сохранятся. Совпадения по ID или содержанию будут пропущены." : "Занятия, которых нет в новом списке, будут удалены из выбранного блока. Проверьте список удалений." }}</p>
        <label class="block">Блок расписания
            <select wire:model="blockId" class="block w-full rounded-lg border p-3">@foreach($blocks as $block)<option value="{{ $block['data']['block_id'] }}">{{ $block['data']['title'] ?? 'Расписание' }}</option>@endforeach</select>
        </label>
        <label class="block">Ссылка на Google Таблицу
            <input wire:model="sourceUrl" type="url" class="block w-full rounded-lg border p-3" placeholder="https://docs.google.com/spreadsheets/d/…/edit?gid=…">
        </label>
        <p>Чтение по ссылке работает, если лист доступен без входа в Google. Для закрытой таблицы скопируйте ячейки с заголовками и вставьте ниже или скачайте лист в CSV. Доступ к исходной таблице не меняется.</p>
        <label class="block">Способ загрузки
            <select wire:model.live="inputMode" class="block w-full rounded-lg border p-3"><option value="paste">Вставить диапазон из таблицы</option><option value="google">Прочитать Google Таблицу по ссылке</option><option value="file">Загрузить CSV или TSV</option></select>
        </label>
        @if($inputMode === 'paste')<label class="block">Ячейки с заголовками «Дата», «Время», «Тема», «Преподаватель»<textarea wire:model="sourceText" rows="7" class="block w-full rounded-lg border p-3"></textarea></label>@endif
        @if($inputMode === 'file')<label class="block">CSV/TSV в UTF-8, до 2 МБ<input type="file" wire:model="csv" accept=".csv,.tsv,.txt" class="block p-3"></label>@endif
        @if($errors->any())<div role="alert" class="rounded-lg border border-red-400 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <x-filament::button type="submit" wire:loading.attr="disabled">Прочитать и проверить строки</x-filament::button>
        <span wire:loading>Обработка…</span>
    </form>
    @endif
    @if($preview)
        <div class="space-y-4">
            <h2 class="text-xl font-semibold">Занятий в предпросмотре: {{ count($preview['lessons']) }}</h2>
            <p>Новых: {{ $preview['counts']['added'] }} / изменённых: {{ $preview['counts']['changed'] }} / без изменений: {{ $preview['counts']['same'] }} / удаляемых: {{ $preview['counts']['removed'] }}</p>
            <p>Заголовки найдены в строке {{ $preview['header'] }}. Пропущено строк других групп: {{ $preview['skipped'] }}.</p>
            @foreach($preview['errors'] as $message)<p class="rounded-lg border border-red-400 p-3">{{ $message }}</p>@endforeach
            @foreach($preview['warnings'] as $message)<p class="rounded-lg border border-amber-400 p-3">{{ $message }}</p>@endforeach
            <p>Месяцы и дни недели определяются по дате. Время — Екатеринбург. Москва отображается настройкой блока. Часы сохраняются из таблицы без пересчёта.</p>
            @foreach(['lessons' => 'Новое расписание', 'removed' => 'Будут убраны из расписания'] as $key => $label)
                @if(count($preview[$key]))
                <details open><summary>{{ $label }}</summary><div class="overflow-auto" style="max-height:400px"><table class="w-full text-sm"><thead><tr>@foreach(['Дата','Время ЕКБ','Тема','Преподаватель','Часы'] as $heading)<th class="border-b p-3 text-left">{{ $heading }}</th>@endforeach</tr></thead><tbody>@foreach($preview[$key] as $lesson)<tr>@foreach(['date','time','title','teacher','hours'] as $field)<td class="border-b p-3 align-top">{{ $lesson[$field] ?? '' }}</td>@endforeach</tr>@endforeach</tbody></table></div></details>
                @endif
            @endforeach
            <p>{{ $preview['mode'] === 'append' ? 'Публикация добавит только новые занятия.' : 'Публикация заменит занятия только выбранного блока.' }} Записи и остальные блоки сохранятся. Ручные ссылки, модули и формат сохранятся у совпавших строк, если этих колонок нет в источнике. Пустая ячейка существующей колонки очистит поле.</p>
            <x-filament::button wire:click="publish" wire:loading.attr="disabled" :disabled="count($preview['errors']) > 0">{{ $preview['mode'] === 'append' ? 'Добавить занятия и опубликовать' : 'Заменить расписание и опубликовать' }}</x-filament::button>
        </div>
    @endif
    @if($resultMessage)<p role="status" class="rounded-lg border border-green-400 p-4">{{ $resultMessage }}</p>@endif

