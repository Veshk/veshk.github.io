<?php

namespace App\Filament\Pages;

use App\Models\CourseOrder;
use App\Models\SelfStudyCourse;
use App\Models\User;
use App\Services\SelfStudyAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.course-orders';

    protected static ?string $title = 'Заказы курсов в записи';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table->query(CourseOrder::whereHas('course', fn ($query) => $query->visibleTo(auth()->user()))->with(['course', 'user']))->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('id')->label('Заказ')->sortable(),
            TextColumn::make('user.name')->label('Слушатель')->searchable()->description(fn (CourseOrder $record): string => $record->user->email)->url(fn (CourseOrder $record): string => Students::getUrl(['student' => $record->user_id])),
            TextColumn::make('course.title')->label('Курс')->wrap(),
            TextColumn::make('amount_minor')->label('Сумма')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' ₽'),
            TextColumn::make('status')->label('Оплата')->formatStateUsing(fn (CourseOrder $record): string => $record->statusLabel())->badge(),
            TextColumn::make('paid_at')->label('Дата оплаты')->dateTime('d.m.Y H:i', timezone: 'Asia/Yekaterinburg')->placeholder('Не подтверждена'),
        ])->filters([SelectFilter::make('status')->label('Оплата')->options(['pending' => 'Ожидает оплаты', 'paid' => 'Оплата подтверждена'])])->deferFilters(false)->headerActions([
            Action::make('create')->label('Создать заказ')->schema([
                Select::make('course_id')->label('Курс в записи')->options(fn (): array => SelfStudyCourse::visibleTo(auth()->user())->where('kind', 'recorded')->pluck('title', 'id')->all())->searchable()->required(),
                CourseAccesses::studentField(),
            ])->action(fn (array $data) => app(SelfStudyAccess::class)->createOrder(SelfStudyCourse::findOrFail($data['course_id']), User::findOrFail($data['user_id']), auth()->user())),
        ])->recordActions([
            Action::make('confirm')->label('Подтвердить оплату')->visible(fn (CourseOrder $record): bool => $record->status === 'pending')->modalDescription('Проверьте фактическое поступление платежа и соответствие курса. Подтверждение откроет доступ с даты оплаты.')->schema([
                TextInput::make('reference')->label('Идентификатор платежа')->required()->maxLength(180),
                TextInput::make('amount')->label('Полученная сумма, ₽')->numeric()->step(0.01)->minValue(1)->required(),
                DateTimePicker::make('paid_at')->label('Дата оплаты, Екатеринбург')->required()->maxDate(now()->timezone('Asia/Yekaterinburg')),
            ])->action(fn (CourseOrder $record, array $data) => app(SelfStudyAccess::class)->confirmPayment($record, auth()->user(), $data['reference'], (int) round($data['amount'] * 100), $data['paid_at'])),
        ])->emptyStateHeading('Заказов пока нет');
    }
}
