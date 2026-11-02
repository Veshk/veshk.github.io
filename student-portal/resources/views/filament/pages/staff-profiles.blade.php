<x-filament-panels::page>
<x-filament::section heading="Фото и контакты администратора">
<p>Фото и общие контакты автоматически отображаются во всех группах сотрудника. Старший администратор может менять любой профиль.</p>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin:24px 0">@foreach($people as $staff)<x-filament::button wire:click="selectPerson({{ $staff->id }})" color="{{ $staff->id === $selectedId ? 'primary' : 'gray' }}">{{ $staff->name }}</x-filament::button>@endforeach</div>
<form wire:submit="save" style="max-width:680px;display:grid;gap:20px">
<h2>{{ $person->name }}</h2>
@if($photo && in_array($photo->getMimeType(), ['image/jpeg','image/png','image/webp']) && !$errors->has('photo'))<img src="{{ $photo->temporaryUrl() }}" alt="Предпросмотр фото" style="width:104px;height:104px;border-radius:50%;object-fit:cover">@elseif($person->profile_photo && !$removePhoto)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person->profile_photo) }}" alt="Фото администратора" style="width:104px;height:104px;border-radius:50%;object-fit:cover">@endif
<label>Фотография администратора<input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" style="display:block;margin-top:8px"></label>
<p>JPG, PNG или WebP, до 2 МБ.</p>@error('photo')<p role="alert">{{ $message }}</p>@enderror
<label><input type="checkbox" wire:model.live="removePhoto"> Убрать текущее фото</label>
<label>Контактная информация<textarea wire:model="contactText" rows="3" style="display:block;width:100%;padding:12px;border:1px solid #ccd7db"></textarea></label>@error('contactText')<p role="alert">{{ $message }}</p>@enderror
<label>Ссылка для связи<input type="url" wire:model="contactUrl" placeholder="https://" style="display:block;width:100%;padding:12px;border:1px solid #ccd7db"></label>@error('contactUrl')<p role="alert">{{ $message }}</p>@enderror
<x-filament::button type="submit" wire:loading.attr="disabled">Сохранить профиль</x-filament::button>
</form>
</x-filament::section>
</x-filament-panels::page>
