<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\StudyGroup;
use App\Support\CoursePage;
use App\Support\GoogleScheduleSource;
use App\Support\ScheduleImport;
use Filament\Pages\Page;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ImportSchedule extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.import-schedule';

    protected static ?string $title = 'Обновить расписание из таблицы';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $groupId;

    public string $blockId = '';

    public string $sourceUrl = '';

    public string $inputMode = 'paste';

    public string $mergeMode = 'append';

    public string $sourceText = '';

    public ?TemporaryUploadedFile $csv = null;

    #[Locked]
    public ?array $preview = null;

    #[Locked]
    public string $snapshot = '';

    #[Locked]
    public string $inputSnapshot = '';

    public string $resultMessage = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function group(): StudyGroup
    {
        $group = StudyGroup::findOrFail($this->groupId);
        abort_unless(auth()->user()->can('update', $group), 403);

        return $group;
    }

    public function mount(): void
    {
        $this->groupId = request()->integer('group');
        $group = $this->group();
        foreach (CoursePage::forGroup($group) as $block) {
            if ($block['type'] === 'schedule') {
                $this->blockId = $block['data']['block_id'];
                $this->sourceUrl = $block['data']['source_url'] ?? '';
                break;
            }
        }
    }

    private function inputHash(): string
    {
        return hash('sha256', json_encode([$this->groupId, $this->blockId, $this->sourceUrl, $this->inputMode, $this->mergeMode, $this->sourceText, $this->csv?->getFilename()]));
    }

    public function prepare(): void
    {
        $group = $this->group();
        $this->preview = null;
        $this->resultMessage = '';
        $this->validate(['blockId' => ['required', 'string', 'max:100'], 'sourceUrl' => ['nullable', 'url:https', 'max:2000'], 'inputMode' => ['in:paste,google,file'], 'mergeMode' => ['required', 'in:append,replace'], 'sourceText' => ['string', 'max:2000000'], 'csv' => ['nullable', 'file', 'max:1953']]);
        $blocks = CoursePage::forGroup($group);
        $block = collect($blocks)->first(fn ($b) => $b['type'] === 'schedule' && $b['data']['block_id'] === $this->blockId);
        abort_unless($block, 422);
        try {
            if ($this->sourceUrl !== '') {
                GoogleScheduleSource::csvUrl($this->sourceUrl);
            }
            $text = match ($this->inputMode) {
                'google' => app(GoogleScheduleSource::class)->read($this->sourceUrl),
                'file' => $this->csv ? file_get_contents($this->csv->getRealPath()) : '',
                default => $this->sourceText,
            };
            $this->preview = ScheduleImport::merge(ScheduleImport::parse($text, $group->code), $block['data']['lessons'] ?? [], $this->mergeMode);
            $this->snapshot = hash('sha256', json_encode([$blocks, $group->code]));
            $this->inputSnapshot = $this->inputHash();
        } catch (InvalidArgumentException $e) {
            $this->addError('sourceText', $e->getMessage());
        } catch (ConnectionException $e) {
            $this->addError('sourceText', 'Google не ответил. Повторите чтение или вставьте диапазон из таблицы.');
        }
    }

    public function publish(): void
    {
        $this->group();
        if (! $this->preview || $this->preview['errors'] || $this->inputSnapshot !== $this->inputHash()) {
            $this->addError('sourceText', 'Сначала проверьте актуальные строки без ошибок.');

            return;
        }
        DB::transaction(function (): void {
            $group = StudyGroup::lockForUpdate()->findOrFail($this->groupId);
            abort_unless(auth()->user()->can('update', $group), 403);
            $blocks = CoursePage::forGroup($group);
            if ($this->snapshot !== hash('sha256', json_encode([$blocks, $group->code]))) {
                $this->preview = null;
                $this->addError('sourceText', 'Страница группы изменилась после предпросмотра. Проверьте строки ещё раз.');

                return;
            }
            foreach ($blocks as &$block) {
                if ($block['type'] === 'schedule' && $block['data']['block_id'] === $this->blockId) {
                    $block['data']['lessons'] = $this->preview['lessons'];
                    $block['data']['source_url'] = $this->sourceUrl;
                    $block['data']['imported_at'] = now()->toIso8601String();
                }
            }
            unset($block);
            $group->update(['page_blocks' => $blocks]);
            $this->preview = null;
            $this->resultMessage = 'Расписание опубликовано. Записи занятий и другие блоки сохранены.';
        });
    }

    protected function getViewData(): array
    {
        $group = $this->group();

        return ['group' => $group, 'blocks' => array_values(array_filter(CoursePage::forGroup($group), fn ($b) => $b['type'] === 'schedule')), 'editorUrl' => StudyGroupResource::getUrl('page', ['record' => $group])];
    }
}
