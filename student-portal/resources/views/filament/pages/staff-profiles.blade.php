<x-filament-panels::page>
{{ $this->table }}
<x-filament::section heading="Аккаунт и профиль администратора">
<p class="portal-intro mb-5">Фото и контакты показываются во всех группах сотрудника. Аккаунт может существовать без групп.</p>
<form wire:submit="save" class="portal-admin-form">
<h2 class="portal-profile-heading">{{ $person->name }}</h2><p>{{ $person->email }} · {{ $person->isSenior() ? 'Старший администратор' : 'Администратор' }}</p>
<fieldset @disabled(!$canEdit)>
@if($photo && in_array($photo->getMimeType(), ['image/jpeg','image/png','image/webp']) && !$errors->has('photo'))<img src="{{ $photo->temporaryUrl() }}" alt="Предпросмотр фото" class="portal-profile-photo">@elseif($person->profile_photo && !$removePhoto)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person->profile_photo) }}" alt="Фото администратора" class="portal-profile-photo">@endif
<label class="portal-field">Фотография администратора<input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="portal-file-input"></label>
<p>JPG, PNG или WebP, до 2 МБ.</p>@error('photo')<p role="alert">{{ $message }}</p>@enderror
<label class="portal-check"><input type="checkbox" wire:model.live="removePhoto"> Убрать текущее фото</label>
<label class="portal-field">Контактная информация<textarea wire:model="contactText" rows="3"></textarea></label>@error('contactText')<p role="alert">{{ $message }}</p>@enderror
<label class="portal-field">Ссылка для связи<input type="url" wire:model="contactUrl" placeholder="https://"></label>@error('contactUrl')<p role="alert">{{ $message }}</p>@enderror
<div><h3>Дополнительная функция</h3><label class="portal-check"><input type="checkbox" wire:model="reviewsUnassigned" @disabled(!auth()->user()->isSenior())> Разбирает заявки без группы</label><p>Помогает слушателю выбрать группу. Настройку меняет старший администратор.</p></div>
</fieldset>
@if($canEdit)<x-filament::button type="submit" wire:loading.attr="disabled">Сохранить профиль</x-filament::button>@else<p>Только просмотр. Изменять профиль может сотрудник или старший администратор.</p>@endif
</form>
</x-filament::section>
</x-filament-panels::page>
