<x-filament-panels::page>
{{ $this->table }}
<x-filament::section heading="Аккаунт и профиль администратора">
<p>Фото и контакты показываются во всех группах сотрудника. Аккаунт может существовать без групп.</p>
<form wire:submit="save" style="max-width:680px;display:grid;gap:20px">
<h2>{{ $person->name }}</h2><p>{{ $person->email }} · {{ $person->isSenior() ? 'Старший администратор' : 'Администратор' }}</p>
<fieldset @disabled(!$canEdit) style="display:grid;gap:20px">
@if($photo && in_array($photo->getMimeType(), ['image/jpeg','image/png','image/webp']) && !$errors->has('photo'))<img src="{{ $photo->temporaryUrl() }}" alt="Предпросмотр фото" style="width:104px;height:104px;border-radius:50%;object-fit:cover">@elseif($person->profile_photo && !$removePhoto)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person->profile_photo) }}" alt="Фото администратора" style="width:104px;height:104px;border-radius:50%;object-fit:cover">@endif
<label>Фотография администратора<input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" style="display:block;margin-top:8px"></label>
<p>JPG, PNG или WebP, до 2 МБ.</p>@error('photo')<p role="alert">{{ $message }}</p>@enderror
<label><input type="checkbox" wire:model.live="removePhoto"> Убрать текущее фото</label>
<label>Контактная информация<textarea wire:model="contactText" rows="3" style="display:block;width:100%;padding:12px;border:1px solid #ccd7db"></textarea></label>@error('contactText')<p role="alert">{{ $message }}</p>@enderror
<label>Ссылка для связи<input type="url" wire:model="contactUrl" placeholder="https://" style="display:block;width:100%;padding:12px;border:1px solid #ccd7db"></label>@error('contactUrl')<p role="alert">{{ $message }}</p>@enderror
<div><h3>Дополнительная функция</h3><label><input type="checkbox" wire:model="reviewsUnassigned" @disabled(!auth()->user()->isSenior())> Разбирает заявки без группы</label><p>Помогает слушателю выбрать группу. Настройку меняет старший администратор.</p></div>
</fieldset>
@if($canEdit)<x-filament::button type="submit" wire:loading.attr="disabled">Сохранить профиль</x-filament::button>@else<p>Только просмотр. Изменять профиль может сотрудник или старший администратор.</p>@endif
</form>
</x-filament::section>
</x-filament-panels::page>
