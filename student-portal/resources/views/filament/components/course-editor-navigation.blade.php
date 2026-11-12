<nav class="course-editor-navigation" aria-label="Блоки для редактирования" x-data="{ labels: @js(\App\Support\CoursePage::BLOCKS) }">
    <h2>Блоки страницы</h2>
    <p>Выберите блок, который хотите изменить.</p>
    <div class="course-editor-navigation-links">
        <button type="button" x-on:click="$dispatch('course-editor-focus', { block: 'hero' })">Главный экран</button>
        <template x-for="(item, key) in $wire.data.page_blocks" :key="key">
            <button type="button" x-on:click="$dispatch('course-editor-focus', { block: item.data.block_id })">
                <span x-text="item.data.title || labels[item.type]"></span>
                <small x-show="item.data.visible === false" x-cloak>Скрыт</small>
            </button>
        </template>
    </div>
</nav>
