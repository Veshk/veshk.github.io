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

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function selectedStudent(): ?User
    {
        abort_unless(static::canAccess(), 403);

        return $this->selectedStudentId ? User::where('role', 'student')->with('enrollments.group')->findOrFail($this->selectedStudentId) : null;
    }

    public function selectStudent(?int $id): void
    {
        $this->selectedStudentId = $id;
        $this->selectedStudent();
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        if ($person = $this->selectedStudent()) {
            return $table->query(PortalVisit::where('user_id', $person->id))->defaultSort('visited_at', 'desc')->columns([
                TextColumn::make('visited_at')->label('Дата и время визита')->dateTime('d.m.Y H:i:s', timezone: 'Asia/Yekaterinburg')->sortable(),
                TextColumn::make('page')->label('Открытая страница')->wrap(),
            ])->emptyStateHeading('Посещений пока нет')->emptyStateDescription('История записывается с момента включения журнала.')->paginated([25, 50, 100]);
        }

        return $table->query(User::where('role', 'student')->with('enrollments.group')->withMax('portalVisits', 'visited_at'))->defaultSort('name')->columns([
            TextColumn::make('name')->label('Слушатель')->searchable(['name', 'email'])->sortable()->description(fn (User $record): string => $record->email)->action(fn (User $record) => $this->selectStudent($record->id)),
            TextColumn::make('access')->label('Доступ')->state(fn (User $record): string => $record->enrollments->isEmpty() ? 'Не открыт' : ($record->enrollments->contains(fn ($enrollment): bool => $enrollment->group->availableTo($record)) ? 'Открыт' : 'Завершён'))->badge()->color(fn (string $state): string => match ($state) {
                'Открыт' => 'success', 'Не открыт' => 'warning', default => 'gray',
            }),
            TextColumn::make('enrollments.group.code')->label('Группы')->listWithLineBreaks()->placeholder('Нет доступа к группам'),
            TextColumn::make('portal_visits_max_visited_at')->label('Последний визит в ЛК')->dateTime('d.m.Y H:i', timezone: 'Asia/Yekaterinburg')->sortable()->placeholder('Ещё не посещал'),
        ])->filters([
            SelectFilter::make('group')->label('Группа')->options(fn () => StudyGroup::orderBy('code')->pluck('code', 'id')->all())->searchable()->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $id) => $query->whereHas('enrollments', fn ($query) => $query->where('study_group_id', $id)))),
            TernaryFilter::make('has_access')->label('Зачислен в группы')->queries(true: fn ($query) => $query->has('enrollments'), false: fn ($query) => $query->doesntHave('enrollments'), blank: fn ($query) => $query),
            TernaryFilter::make('has_visits')->label('Есть посещения ЛК')->queries(true: fn ($query) => $query->has('portalVisits'), false: fn ($query) => $query->doesntHave('portalVisits'), blank: fn ($query) => $query),
        ])->deferFilters(false)->recordActions([
            Action::make('history')->label('История посещений')->icon('heroicon-o-clock')->action(fn (User $record) => $this->selectStudent($record->id)),
        ])->emptyStateHeading('Слушатели не найдены')->paginated([25, 50, 100]);
    }

    protected function getViewData(): array
    {
        return ['person' => $this->selectedStudent()];
    }
}
