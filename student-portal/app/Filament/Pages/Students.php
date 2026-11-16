<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EnrollmentApplications\EnrollmentApplicationResource;
use App\Models\PortalVisit;
use App\Models\StudyGroup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    #[Url(as: 'search')]
    public $tableSearch = '';

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

        return $this->selectedStudentId ? $this->directoryQuery($this->selectedGroup())->findOrFail($this->selectedStudentId) : null;
    }

    protected function visiblePendingApplications(Builder $query): Builder
    {
        return $query->when(! auth()->user()->reviews_unassigned, fn (Builder $query) => $query->whereNotNull('study_group_id'));
    }

    protected function inGroup(Builder $query, int $groupId): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereHas('enrollments', fn (Builder $query) => $query->where('study_group_id', $groupId))
            ->orWhereHas('pendingApplications', fn (Builder $query) => $query->where('study_group_id', $groupId)));
    }

    protected function directoryQuery(?StudyGroup $group): Builder
    {
        return User::where('role', 'student')
            ->when($group, fn (Builder $query) => $this->inGroup($query, $group->id))
            ->with(['enrollments.group', 'selfStudyEnrollments.course.lessons', 'courseOrders.course',
                'pendingApplications' => fn ($query) => $this->visiblePendingApplications($query->getQuery())->with('group'),
            ]);
    }

    public function applicationListUrl(User $student, ?int $groupId = null): string
    {
        return EnrollmentApplicationResource::getUrl(parameters: [
            'search' => $student->email,
            'filters' => ['status' => ['value' => 'pending'], 'study_group_id' => ['value' => $groupId]],
        ]);
    }

    protected function accessLabel(User $student, ?StudyGroup $group): string
    {
        $enrollments = $student->enrollments->when($group, fn ($items) => $items->where('study_group_id', $group->id));
        $pending = $student->pendingApplications->when($group, fn ($items) => $items->where('study_group_id', $group->id));
        if ($enrollments->isEmpty() && ($group || $student->selfStudyEnrollments->isEmpty())) {
            return $pending->isNotEmpty() ? 'Ожидает одобрения' : 'Не открыт';
        }

        $available = $enrollments->contains(fn ($enrollment) => $enrollment->group->availableTo($student))
            || (! $group && $student->selfStudyEnrollments->contains(fn ($enrollment) => $enrollment->available()));

        return $available ? 'Открыт' : 'Завершён';
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

        return $table->query($this->directoryQuery($group)->withMax('portalVisits', 'visited_at'))->defaultSort('name')->columns([
            TextColumn::make('name')->label('Слушатель')->searchable(['name', 'email'])->sortable()->description(fn (User $record): string => $record->email)->action(fn (User $record) => $this->selectStudent($record->id)),
            TextColumn::make('access')->label('Доступ')->state(fn (User $record): string => $this->accessLabel($record, $group))->badge()->wrap()->color(fn (string $state): string => match ($state) {
                'Открыт' => 'success', 'Не открыт', 'Ожидает одобрения' => 'warning', default => 'gray',
            }),
            ViewColumn::make('groups')->label('Группы и заявки')->view('filament.components.student-groups'),
            TextColumn::make('selfStudyEnrollments.course.title')->label('Курсы')->listWithLineBreaks()->placeholder('Курсов пока нет'),
            TextColumn::make('portal_visits_max_visited_at')->label('Последний визит в ЛК')->dateTime('d.m.Y H:i', timezone: 'Asia/Yekaterinburg')->sortable()->placeholder('Ещё не посещал'),
        ])->filters([
            SelectFilter::make('learning_kind')->label('Вид обучения')->options(['program' => 'Программа обучения', 'free' => 'Бесплатный курс', 'recorded' => 'Курс в записи'])->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $kind) => $kind === 'program' ? $query->where(fn ($query) => $query->has('enrollments')->orWhereHas('pendingApplications', fn ($query) => $query->whereNotNull('study_group_id'))) : $query->whereHas('selfStudyEnrollments.course', fn ($query) => $query->where('kind', $kind)))),
            SelectFilter::make('group')->label('Группа')->visible(! $group)->options(fn () => StudyGroup::orderBy('code')->pluck('code', 'id')->all())->searchable()->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $id) => $this->inGroup($query, (int) $id))),
            SelectFilter::make('membership')->label('Заявки и зачисление')->options(['pending' => 'Ожидает одобрения', 'enrolled' => 'Зачислен'])->query(function (Builder $query, array $data) use ($group): Builder {
                $groupId = $group?->id ?? ($this->getTableFilterState('group')['value'] ?? null);

                return match ($data['value'] ?? null) {
                    'pending' => $query->whereHas('pendingApplications', fn (Builder $query) => $this->visiblePendingApplications($query)->when($groupId, fn ($query) => $query->where('study_group_id', $groupId))),
                    'enrolled' => $query->whereHas('enrollments', fn ($query) => $query->when($groupId, fn ($query) => $query->where('study_group_id', $groupId))),
                    default => $query,
                };
            }),
            TernaryFilter::make('has_access')->label('Зачислен в группы')->queries(true: fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->when($group, fn ($query) => $query->where('study_group_id', $group->id))), false: fn ($query) => $query->whereDoesntHave('enrollments', fn ($query) => $query->when($group, fn ($query) => $query->where('study_group_id', $group->id))), blank: fn ($query) => $query),
            TernaryFilter::make('has_visits')->label('Есть посещения ЛК')->queries(true: fn ($query) => $query->has('portalVisits'), false: fn ($query) => $query->doesntHave('portalVisits'), blank: fn ($query) => $query),
        ])->deferFilters(false)->recordActions([
            Action::make('applications')->label('Заявки')->button()->color('warning')
                ->visible(fn (User $record) => $record->pendingApplications->when($group, fn ($items) => $items->where('study_group_id', $group->id))->isNotEmpty())
                ->url(fn (User $record) => $this->applicationListUrl($record, $group?->id)),
            Action::make('history')->label('Карточка слушателя')->button()->color('gray')->icon('heroicon-o-clock')->action(fn (User $record) => $this->selectStudent($record->id)),
        ])->emptyStateHeading($group ? 'Участники не найдены' : 'Слушатели не найдены')->emptyStateDescription($group ? 'Здесь отображаются зачисленные слушатели и ожидающие одобрения. Проверьте поиск и фильтры.' : null)->paginated([25, 50, 100]);
    }

    protected function getViewData(): array
    {
        $person = $this->selectedStudent();
        $group = $this->selectedGroup();

        return ['person' => $person, 'visits' => $person ? PortalVisit::where('user_id', $person->id)->orderByDesc('visited_at')->orderByDesc('id')->paginate(25, ['*'], 'historyPage', max(1, $this->historyPage)) : null, 'group' => $group,
            'participantCount' => $group?->enrollments()->whereHas('user', fn ($query) => $query->where('role', 'student'))->count(),
            'pendingCount' => $group?->applications()->where('status', 'pending')->whereHas('user', fn ($query) => $query->where('role', 'student'))->count(),
            'groupApplicationsUrl' => $group ? EnrollmentApplicationResource::getUrl(parameters: ['filters' => ['status' => ['value' => 'pending'], 'study_group_id' => ['value' => $group->id]]]) : null,
        ];
    }
}
