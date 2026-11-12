    <section class="section{{ $compact ? ' welcome-section' : '' }}{{ $paired ? ' welcome-paired' : '' }}" id="block-{{ $i }}">
        <span class="course-block-anchor" id="course-block-{{ $block['editor_block_id'] ?? $block['data']['block_id'] }}"></span>
        @can('update', $group)<div class="block-edit-tools"><a class="block-edit-link" href="{{ \App\Filament\Resources\StudyGroups\StudyGroupResource::getUrl('page', ['record'=>$group, 'block'=>$block['editor_block_id'] ?? $block['data']['block_id']]) }}" aria-label="Редактировать блок: {{ $block['data']['title'] ?? '' }}"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15v5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>Редактировать</a></div>@endcan
        @if(! $compact)<div class="section-title"><h2>{{ $block['data']['title'] ?? \App\Support\CoursePage::BLOCKS[$block['type']] }}</h2></div>@endif
        @include('student.page-block', ['type' => $block['type'], 'data' => $block['data'], 'group' => $block['source_group_model'] ?? $group, 'isAlias' => isset($block['source_group_model'])])
    </section>
