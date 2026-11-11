<?php

namespace App\Filament\Pages;

use App\Models\PortalVisit;
use App\Models\StudyGroup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

class Students extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.students';

    protected static ?string $title = 'Слушатели';

    protected static ?string $navigationLabel = 'Слушатели';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 2;

    #[Url(as: 'student')]
    public ?int $selectedStudentId = null;

    #[Url(as: 'group')]
    public ?int $selectedGroupId = null;

    public int $historyPage = 1;

    protected function selectedGroup(): ?StudyGroup
    {
        abort_unless(static::canAccess(), 403);

        return $this->selectedGroupId ? StudyGroup::findOrFail($this->selectedGroupId) : null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function selectedStudent(): ?User
    {
        abort_unless(static::canAccess(), 403);

        return $this->selectedStudentId ? User::where('role', 'student')->when($this->selectedGroupId, fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->where('study_group_id', $this->selectedGroupId)))->with('enrollments.group')->findOrFail($this->selectedStudentId) : null;
    }

    public function selectStudent(?int $id): void
    {
        $this->selectedStudentId = $id;
        $this->selectedStudent();
        $this->historyPage = 1;
        $this->dispatch($id ? 'open-modal' : 'close-modal', id: 'student-history');
    }

    public function changeHistoryPage(int $page): void
    {
        $person = $this->selectedStudent();
        abort_unless($person, 404);
        $this->historyPage = max(1, min($page, (int) ceil($person->portalVisits()->count() / 25)));
    }

    public function table(Table $table): Table
    {
        $group = $this->selectedGroup();

        return $table->query(User::where('role', 'student')->when($group, fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->where('study_group_id', $group->id)))->with('enrollments.group')->withMax('portalVisits', 'visited_at'))->defaultSort('name')->columns([
            TextColumn::make('name')->label('Слушатель')->searchable(['name', 'email'])->sortable()->description(fn (User $record): string => $record->email)->action(fn (User $record) => $this->selectStudent($record->id)),
            TextColumn::make('access')->label('Доступ')->state(fn (User $record): string => $record->enrollments->isEmpty() ? 'Не открыт' : (($group ? $group->availableTo($record) : $record->enrollments->contains(fn ($enrollment): bool => $enrollment->group->availableTo($record))) ? 'Открыт' : 'Завершён'))->badge()->color(fn (string $state): string => match ($state) {
                'Открыт' => 'success', 'Не открыт' => 'warning', default => 'gray',
            }),
            TextColumn::make('enrollments.group.code')->label('Группы')->listWithLineBreaks()->placeholder('Нет доступа к группам'),
            TextColumn::make('portal_visits_max_visited_at')->label('Последний визит в ЛК')->dateTime('d.m.Y H:i', timezone: 'Asia/Yekaterinburg')->sortable()->placeholder('Ещё не посещал'),
        ])->filters([
            SelectFilter::make('group')->label('Группа')->visible(! $group)->options(fn () => StudyGroup::orderBy('code')->pluck('code', 'id')->all())->searchable()->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $id) => $query->whereHas('enrollments', fn ($query) => $query->where('study_group_id', $id)))),
            TernaryFilter::make('has_access')->label('Зачислен в группы')->queries(true: fn ($query) => $query->has('enrollments'), false: fn ($query) => $query->doesntHave('enrollments'), blank: fn ($query) => $query),
            TernaryFilter::make('has_visits')->label('Есть посещения ЛК')->queries(true: fn ($query) => $query->has('portalVisits'), false: fn ($query) => $query->doesntHave('portalVisits'), blank: fn ($query) => $query),
        ])->deferFilters(false)->recordActions([
            Action::make('history')->label('История посещений')->button()->color('gray')->icon('heroicon-o-clock')->action(fn (User $record) => $this->selectStudent($record->id)),
        ])->emptyStateHeading($group ? 'Участники не найдены' : 'Слушатели не найдены')->emptyStateDescription($group ? 'Здесь отображаются зачисленные слушатели. Проверьте поиск и фильтры; заявки на проверке в список не входят.' : null)->paginated([25, 50, 100]);
    }

    protected function getViewData(): array
    {
        $person = $this->selectedStudent();

        return ['person' => $person, 'visits' => $person ? PortalVisit::where('user_id', $person->id)->orderByDesc('visited_at')->orderByDesc('id')->paginate(25, ['*'], 'historyPage', max(1, $this->historyPage)) : null, 'group' => $this->selectedGroup(), 'participantCount' => $this->selectedGroup()?->enrollments()->whereHas('user', fn ($query) => $query->where('role', 'student'))->count()];
    }
}
