<?php

namespace App\Filament\Pages;

use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\User;
use App\Services\SelfStudyAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

class CourseAccesses extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.course-accesses';

    protected static ?string $title = 'Доступы к курсам';

    #[Url(as: 'course')]
    public ?int $courseId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function course(): ?SelfStudyCourse
    {
        return $this->courseId ? SelfStudyCourse::visibleTo(auth()->user())->findOrFail($this->courseId) : null;
    }

    public function table(Table $table): Table
    {
        $course = $this->course();

        return $table->query(SelfStudyEnrollment::whereHas('course', fn ($query) => $query->visibleTo(auth()->user()))->when($course, fn ($query) => $query->where('self_study_course_id', $course->id))->with(['user', 'course.lessons']))->columns([
            TextColumn::make('user.name')->label('Слушатель')->searchable()->description(fn (SelfStudyEnrollment $record): string => $record->user->email)->url(fn (SelfStudyEnrollment $record): string => Students::getUrl(['student' => $record->user_id])),
            TextColumn::make('course.title')->label('Курс')->wrap()->description(fn (SelfStudyEnrollment $record): string => $record->course->kindLabel()),
            TextColumn::make('status')->label('Доступ')->state(fn (SelfStudyEnrollment $record): string => $record->statusLabel())->badge(),
            TextColumn::make('expires_at')->label('Доступ до')->dateTime('d.m.Y H:i', timezone: 'Asia/Yekaterinburg')->sortable(),
            TextColumn::make('progress')->label('Прогресс')->state(fn (SelfStudyEnrollment $record): string => $record->progress().'%'),
        ])->headerActions([
            Action::make('grant')->label('Открыть бесплатный курс')->schema([
                Select::make('course_id')->label('Бесплатный курс')->options(fn (): array => SelfStudyCourse::visibleTo(auth()->user())->where('kind', 'free')->pluck('title', 'id')->all())->default($course?->kind === 'free' ? $course->id : null)->searchable()->required(),
                self::studentField(),
            ])->action(function (array $data): void {
                app(SelfStudyAccess::class)->grantFree(SelfStudyCourse::findOrFail($data['course_id']), User::findOrFail($data['user_id']), auth()->user());
            }),
        ])->recordActions([
            Action::make('extend')->label('Продлить')->schema([
                DatePicker::make('until')->label('Новая дата окончания')->required(),
                Textarea::make('reason')->label('Причина')->required()->maxLength(1000),
            ])->action(fn (SelfStudyEnrollment $record, array $data) => app(SelfStudyAccess::class)->extend($record, auth()->user(), $data['until'], $data['reason'])),
            Action::make('history')->label('История')->modalHeading('История доступа')->modalContent(fn (SelfStudyEnrollment $record) => view('filament.components.course-access-history', ['history' => $record->history ?? []]))->modalSubmitAction(false),
            Action::make('revoke')->label('Закрыть')->color('danger')->visible(fn (SelfStudyEnrollment $record): bool => ! $record->revoked_at)->requiresConfirmation()->schema([Textarea::make('reason')->label('Причина закрытия')->required()->maxLength(1000)])->action(fn (SelfStudyEnrollment $record, array $data) => app(SelfStudyAccess::class)->revoke($record, auth()->user(), $data['reason'])),
        ])->emptyStateHeading('Доступов пока нет');
    }

    public static function studentField(): Select
    {
        return Select::make('user_id')->label('Слушатель')->searchable()->required()
            ->getSearchResultsUsing(fn (string $search): array => User::where('role', 'student')->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))->limit(30)->get()->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' — '.$user->email])->all())
            ->getOptionLabelUsing(fn ($value): ?string => User::where('role', 'student')->find($value)?->email);
    }

    protected function getViewData(): array
    {
        return ['course' => $this->course()];
    }
}
