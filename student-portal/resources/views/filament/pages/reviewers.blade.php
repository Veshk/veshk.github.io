<x-filament-panels::page>
    <x-filament::section heading="Кто разбирает заявки без группы">
        <p style="margin-bottom:24px;max-width:680px">Выберите одного или нескольких сотрудников. Им будут доступны обращения «Не знаю свою группу / нужна помощь». Основные права на группы остаются прежними.</p>
        <form wire:submit="save">
            @foreach (\App\Models\User::whereIn('role',['admin','senior'])->orderBy('name')->get() as $person)
                <label style="display:flex;gap:14px;padding:18px 0;border-bottom:1px solid #d8e0e3;align-items:center">
                    <input type="checkbox" wire:model="selected" value="{{ $person->id }}">
                    <span><strong>{{ $person->name }}</strong><br><span style="color:#66757c">{{ $person->isSenior() ? 'Старший администратор' : 'Администратор' }}</span></span>
                </label>
            @endforeach
            <div style="margin-top:24px"><x-filament::button type="submit">Сохранить ответственных</x-filament::button></div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
