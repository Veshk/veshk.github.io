<x-filament-panels::page>
    <p class="portal-intro">Выберите вид обучения для настройки содержания или найдите человека в общем списке слушателей</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr));gap:24px">
        <x-filament::section heading="Программы обучения"><p>Учебные группы, расписание, записи занятий и администраторы</p><br><x-filament::button tag="a" :href="\App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl()">Открыть группы</x-filament::button></x-filament::section>
        <x-filament::section heading="Бесплатные курсы"><p>Уроки, материалы и персональный доступ на месяц</p><br><x-filament::button tag="a" :href="\App\Filament\Pages\CourseCatalog::getUrl(['kind' => 'free'])">Управлять курсами</x-filament::button></x-filament::section>
        <x-filament::section heading="Курсы в записи"><p>Уроки, материалы, заказы, оплата и срок доступа</p><br><x-filament::button tag="a" :href="\App\Filament\Pages\CourseCatalog::getUrl(['kind' => 'recorded'])">Управлять курсами</x-filament::button></x-filament::section>
    </div>
    <x-filament::section heading="Слушатели"><p>Общая карточка человека со всеми программами, курсами, заказами и историей посещений</p><br><x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\Students::getUrl()">Открыть общий список</x-filament::button></x-filament::section>
</x-filament-panels::page>
