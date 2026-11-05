<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Livewire\WithPagination;

class Schedules extends ImportSchedule
{
    use WithPagination;

    protected string $view = 'filament.pages.schedules';

    protected static ?string $title = 'Расписание';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    public string $search = '';

    public string $owner = '';

    public bool $onlyMine = false;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->groupId = 0;
        if (request()->integer('group')) {
            $this->selectGroup(request()->integer('group'));
        }
    }

    public function selectGroup(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $group = StudyGroup::findOrFail($id);
        $this->groupId = $id;
        $this->preview = null;
        $this->sourceText = '';
        $this->sourceUrl = '';
        $this->resultMessage = '';
        $this->blockId = '';
        $this->mergeMode = 'append';
        $this->inputMode = 'paste';
        $this->csv = null;
        $this->resetValidation();
        foreach (CoursePage::forGroup($group) as $block) {
            if ($block['type'] === 'schedule') {
                $this->blockId = $block['data']['block_id'];
                $this->sourceUrl = $block['data']['source_url'] ?? '';
                break;
            }
        }
    }

    public function editTable(): void
    {
        $group = $this->group();
        $block = collect(CoursePage::forGroup($group))->first(fn ($b) => $b['type'] === 'schedule' && $b['data']['block_id'] === $this->blockId);
        abort_unless($block, 422);
        $lessons = $block['data']['lessons'] ?? [];
        $columns = ['date' => 'Дата', 'time' => 'Время', 'title' => 'Тема', 'teacher' => 'Преподаватель', 'hours' => 'Часы', 'module' => 'Модуль', 'format' => 'Формат', 'url' => 'Ссылка'];
        if ($lessons && collect($lessons)->every(fn ($row) => filled($row['source_id'] ?? null))) {
            $columns = ['source_id' => 'ID занятия'] + $columns;
        }
        $cell = fn ($value): string => '"'.str_replace('"', '""', (string) ($value ?? '')).'"';
        $lines = [implode("\t", array_map($cell, array_values($columns)))];
        foreach ($lessons as $lesson) {
            $lines[] = implode("\t", array_map(fn ($key) => $cell($lesson[$key] ?? ''), array_keys($columns)));
        }
        $this->sourceText = implode("\n", $lines);
        $this->inputMode = 'paste';
        $this->mergeMode = 'replace';
        $this->preview = null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOwner(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyMine(): void
    {
        $this->resetPage();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $groups = StudyGroup::query()->with('administrator')->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q->where('title', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')))->when($this->owner !== '', fn ($query) => $query->where('administrator_id', $this->owner))->when($this->onlyMine, fn ($query) => $query->where('administrator_id', auth()->id()))->orderBy('title')->paginate(25);
        $group = $this->groupId ? StudyGroup::findOrFail($this->groupId) : null;

        return ['groups' => $groups, 'staff' => User::whereIn('role', ['admin', 'senior'])->orderBy('name')->get(), 'group' => $group, 'blocks' => $group ? array_values(array_filter(CoursePage::forGroup($group), fn ($b) => $b['type'] === 'schedule')) : [], 'editorUrl' => $group ? StudyGroupResource::getUrl('page', ['record' => $group]) : null];
    }
}
