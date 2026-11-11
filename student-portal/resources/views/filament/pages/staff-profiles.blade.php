<x-filament-panels::page>
{{ $this->table }}
<x-filament::modal id="staff-profile" heading="Аккаунт и профиль администратора" width="3xl" sticky-footer sticky-header>
<p class="portal-intro mb-5">Фото и контакты показываются во всех группах сотрудника. Аккаунт может существовать без групп.</p>
<form id="staff-profile-form" wire:submit="save" class="portal-admin-form">
<h2 class="portal-profile-heading">{{ $person->name }}</h2><p>{{ $person->email }} {{ $person->isSenior() ? 'Старший администратор' : 'Администратор' }}</p>
<fieldset @disabled(!$canEdit)>
<div class="portal-photo-framing" x-data="{ position: $wire.entangle('photoPosition') }">
@if($photo && in_array($photo->getMimeType(), ['image/jpeg','image/png','image/webp']) && !$errors->has('photo'))<img src="{{ $photo->temporaryUrl() }}" alt="Предпросмотр фото" class="portal-profile-photo" style="object-position:50% {{ $photoPosition }}%" :style="'object-position:50% ' + position + '%'">@elseif($person->profile_photo && !$removePhoto)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person->profile_photo) }}" alt="Фото администратора" class="portal-profile-photo" style="object-position:50% {{ $photoPosition }}%" :style="'object-position:50% ' + position + '%'">@endif
<label class="portal-field">Положение фото по вертикали<input type="range" min="0" max="100" step="1" x-model.number="position" aria-label="Положение фото по вертикали"></label>
<p>Перемещайте ползунок, чтобы лицо оказалось по центру круга.</p>
</div>
@error('photoPosition')<p role="alert">{{ $message }}</p>@enderror
<label class="portal-field">Фотография администратора<input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="portal-file-input"></label>
<p>JPG, PNG или WebP, до 2 МБ.</p>@error('photo')<p role="alert">{{ $message }}</p>@enderror
<label class="portal-check"><input type="checkbox" wire:model.live="removePhoto"> Убрать текущее фото</label>
<label class="portal-field">Контактная информация<textarea wire:model="contactText" rows="3"></textarea></label>@error('contactText')<p role="alert">{{ $message }}</p>@enderror
<label class="portal-field">Ссылка для связи<input type="url" wire:model="contactUrl" placeholder="https://"></label>@error('contactUrl')<p role="alert">{{ $message }}</p>@enderror
<div><h3>Дополнительная функция</h3><label class="portal-check"><input type="checkbox" wire:model="reviewsUnassigned" @disabled(!auth()->user()->isSenior())> Разбирает заявки без группы</label><p>Помогает слушателю выбрать группу. Настройку меняет старший администратор.</p></div>
</fieldset>
@unless($canEdit)<p>Только просмотр. Изменять профиль может сотрудник или старший администратор.</p>@endunless
</form>
<x-slot name="footer">
@if($canEdit)<x-filament::button type="submit" form-id="staff-profile-form" wire:loading.attr="disabled">Сохранить профиль</x-filament::button>@endif
<x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'staff-profile'})">Закрыть</x-filament::button>
</x-slot>
</x-filament::modal>
</x-filament-panels::page>
