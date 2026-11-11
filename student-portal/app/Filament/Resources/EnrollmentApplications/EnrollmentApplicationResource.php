<?php

namespace App\Filament\Resources\EnrollmentApplications;

use App\Filament\Resources\EnrollmentApplications\Pages\ListEnrollmentApplications;
use App\Models\EnrollmentApplication;
use App\Models\StudyGroup;
use App\Services\Admissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentApplicationResource extends Resource
{
    protected static ?string $model = EnrollmentApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = 'Заявки на обучение';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'Заявки на обучение';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'group.administrator', 'reviewer', 'mailPreview'])
            ->when(! auth()->user()->reviews_unassigned, fn (Builder $query) => $query->whereNotNull('study_group_id'));
    }

    public static function table(Table $table): Table
    {
        return $table->extraAttributes(['class' => 'portal-applications'])
            ->defaultSort('created_at', 'desc')->recordAction('details')->columns([
                TextColumn::make('user.name')->label('Слушатель')->searchable(['name', 'email'])->sortable()->wrap()
                    ->description(fn (EnrollmentApplication $record) => $record->user->email),
                TextColumn::make('group.title')->label('Группа')->placeholder('Группа не выбрана')->searchable(['title', 'code'])->wrap()
                    ->description(fn (EnrollmentApplication $record) => $record->group ? $record->group->code.' '.$record->group->administrator?->name : 'Нужна помощь с выбором'),
                TextColumn::make('status')->label('Статус')->badge()->wrap()
                    ->description(fn (EnrollmentApplication $record) => 'Подана '.$record->created_at->format('d.m.Y H:i').(auth()->user()->can('review', $record) ? '' : ' Только просмотр'))
                    ->formatStateUsing(fn ($state, EnrollmentApplication $record) => $record->statusLabel())->color(fn ($state) => match ($state) {
                        'approved' => 'success', 'rejected' => 'danger', default => 'warning'
                    }),
            ])->filters([
                SelectFilter::make('status')->label('Статус')->options(['pending' => 'Ожидает проверки', 'approved' => 'Одобрена', 'rejected' => 'Отклонена']),
                SelectFilter::make('study_group_id')->label('Группа')->options(fn () => StudyGroup::pluck('title', 'id')->all()),
            ])->deferFilters(false)->recordActionsColumnLabel('Действия')->recordActions([
                Action::make('details')->label('Открыть заявку')->button()->modalHeading('Заявка на обучение')->modalWidth('2xl')
                    ->modalContent(fn (EnrollmentApplication $record) => view('filament.application-details', ['application' => $record]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Закрыть')->stickyModalFooter()
                    ->extraModalFooterActions(fn (): array => static::reviewActions()),
            ])->emptyStateHeading('Заявок пока нет')->emptyStateDescription('Новые заявки появятся здесь после регистрации слушателей.');
    }

    /** @return array<Action> */
    private static function reviewActions(): array
    {
        return [
            Action::make('approve')->label('Открыть доступ')->cancelParentActions()->color('success')->icon('heroicon-o-check')->visible(fn (EnrollmentApplication $record) => $record->status === 'pending' && $record->study_group_id && auth()->user()->can('review', $record))
                ->modalHeading('Открыть доступ к группе')->modalSubmitActionLabel('Открыть доступ')->modalDescription('Подтвердите, что слушатель действительно учится в выбранной группе.')->schema([
                    Checkbox::make('send_email')->label('Отправить письмо слушателю')->helperText('В прототипе письмо сохраняется в перехватчик и не отправляется на почту.')->default(true),
                ])->action(function (EnrollmentApplication $record, array $data): void {
                    app(Admissions::class)->approve(auth()->user(), $record, (bool) ($data['send_email'] ?? false));
                    Notification::make()->title('Заявка одобрена. Доступ открыт.')->success()->send();
                }),
            Action::make('assign')->label(fn (EnrollmentApplication $record) => $record->study_group_id ? 'Изменить группу' : 'Назначить группу')->cancelParentActions()->modalHeading('Группа слушателя')->modalWidth('lg')->fillForm(fn (EnrollmentApplication $record) => ['study_group_id' => $record->study_group_id])->icon('heroicon-o-arrow-right')->visible(fn (EnrollmentApplication $record) => $record->status === 'pending' && auth()->user()->can('review', $record))
                ->modalSubmitActionLabel('Направить в группу')->schema([Select::make('study_group_id')->label('Нужная группа')->options(fn () => StudyGroup::get()->mapWithKeys(fn ($group) => [$group->id => $group->title.' — '.$group->code])->all())->searchable()->required()])
                ->action(function (EnrollmentApplication $record, array $data): void {
                    app(Admissions::class)->assign(auth()->user(), $record, (int) $data['study_group_id']);
                    Notification::make()->title('Заявка направлена администратору группы')->success()->send();
                }),
            Action::make('reject')->label('Отклонить')->cancelParentActions()->modalHeading('Отклонить заявку')->modalWidth('lg')->color('danger')->visible(fn (EnrollmentApplication $record) => $record->status === 'pending' && auth()->user()->can('review', $record))
                ->modalSubmitActionLabel('Отклонить заявку')->schema([Textarea::make('note')->label('Причина для слушателя')->required()->maxLength(1000)])
                ->action(function (EnrollmentApplication $record, array $data): void {
                    app(Admissions::class)->reject(auth()->user(), $record, $data['note']);
                    Notification::make()->title('Заявка отклонена')->send();
                }),
            Action::make('email')->label('Письмо')->icon('heroicon-o-envelope')->visible(fn (EnrollmentApplication $record) => $record->mailPreview !== null)->url(fn (EnrollmentApplication $record) => $record->mailPreview ? route('mail.preview', $record->mailPreview) : null)->openUrlInNewTab(),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListEnrollmentApplications::route('/')];
    }
}
