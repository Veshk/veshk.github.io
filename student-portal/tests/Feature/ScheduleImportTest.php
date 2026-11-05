<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportSchedule;
use App\Filament\Resources\StudyGroups\Pages\EditGroupPage;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\GoogleScheduleSource;
use App\Support\ScheduleImport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function input(): string
    {
        return "Расписание программы\nУчебный пример\n\nМесяц\tДата\tДень\tВремя\tТема\tПреподаватель\tЧасы\tID занятия\nНоябрь\t06.11.2026\tпятница\t10.00-19.00\tФинансы\tПримеров\t10\tone\n\t11.11.2026\tсреда\t17.00-21.00 Бюджетирование\t\tПримеров\t5\ttwo";
    }

    private function group(): StudyGroup
    {
        return StudyGroup::factory()->create(['page_blocks' => [
            ['type' => 'schedule', 'data' => ['block_id' => 'schedule', 'title' => 'Расписание', 'lessons' => [['source_id' => 'one', 'date' => '2026-11-05', 'time' => '10:00–19:00', 'title' => 'Финансы', 'teacher' => 'Примеров', 'url' => 'https://example.com/join', 'module' => 'Модуль 1'], ['source_id' => 'old', 'date' => '2026-11-02', 'title' => 'Убираемая строка']]]],
            ['type' => 'recordings', 'data' => ['block_id' => 'videos', 'title' => 'Записи', 'videos' => [['title' => 'Сохранить видео', 'url' => 'https://example.com/video']]]],
        ]]);
    }

    public function test_school_template_is_read_with_preamble_months_and_topic_in_time_cell(): void
    {
        $result = ScheduleImport::parse($this->input());
        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['lessons']);
        $this->assertSame(4, $result['header']);
        $this->assertSame('2026-11-06', $result['lessons'][0]['date']);
        $this->assertSame('10:00–19:00', $result['lessons'][0]['time']);
        $this->assertSame('10', $result['lessons'][0]['hours']);
        $this->assertSame('Бюджетирование', $result['lessons'][1]['title']);
        $this->assertCount(1, $result['warnings']);
    }

    public function test_bad_dates_times_duplicates_and_unsafe_links_block_publication(): void
    {
        $text = "Дата;Время;Тема;ID занятия;Ссылка\n31.02.2026;25:00-17:00;Тест;one;javascript:alert(1)\n06.11.2026;19:00-10:00;Тест;one;";
        $result = ScheduleImport::parse($text);
        $this->assertGreaterThanOrEqual(5, count($result['errors']));
        $this->assertNotEmpty(ScheduleImport::parse("Дата\tВремя\tТема")['errors']);
        $this->assertNotEmpty(ScheduleImport::parse($this->input()."\nНоябрь\t06.11.2026\tпятница\t10.00-19.00\tФинансы\tПримеров\t10\tduplicate")['errors']);
    }

    public function test_quoted_csv_and_exact_group_filter_and_missing_id_warning(): void
    {
        $result = ScheduleImport::parse("ТЕГ группы,Дата,Время,Занятие,Преподаватель\nКД-1,06.11.2026,10:00–19:00,\"Финансы, бюджет\",Примеров\nКД-10,07.11.2026,10:00–19:00,Чужая группа,Примеров", 'КД-1');
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['lessons']);
        $this->assertSame('Финансы, бюджет', $result['lessons'][0]['title']);
        $this->assertSame(1, $result['skipped']);
        $this->assertCount(1, $result['warnings']);
    }

    public function test_preview_publish_and_repeat_preserve_other_blocks_and_manual_extras(): void
    {
        $group = $this->group();
        $before = $group->page_blocks;
        $this->actingAs($group->administrator);
        $page = Livewire::withQueryParams(['group' => $group->id])->test(ImportSchedule::class)
            ->set('mergeMode', 'replace')->set('sourceText', $this->input())->call('prepare')->assertHasNoErrors()
            ->assertSet('preview.counts.changed', 1)->assertSet('preview.counts.added', 1)->assertSet('preview.counts.removed', 1);
        $this->assertSame($before, $group->fresh()->page_blocks);
        $page->call('publish')->assertHasNoErrors()->assertSet('preview', null);
        $blocks = $group->fresh()->page_blocks;
        $this->assertSame($before[1], $blocks[1]);
        $this->assertSame('https://example.com/join', $blocks[0]['data']['lessons'][0]['url']);
        $this->assertSame('Модуль 1', $blocks[0]['data']['lessons'][0]['module']);
        $this->assertCount(2, $blocks[0]['data']['lessons']);
        $page->call('prepare')->assertSet('preview.counts.added', 0)->assertSet('preview.counts.removed', 0)->assertSet('preview.counts.same', 2)->call('publish')->assertHasNoErrors();
        $this->assertSame($blocks[0]['data']['lessons'], $group->fresh()->page_blocks[0]['data']['lessons']);
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame('one', $group->fresh()->page_blocks[0]['data']['lessons'][0]['source_id']);
        $this->assertSame('10', $group->fresh()->page_blocks[0]['data']['lessons'][0]['hours']);
    }

    public function test_edited_input_and_concurrent_page_changes_require_new_preview(): void
    {
        $group = $this->group();
        $this->actingAs($group->administrator);
        $page = Livewire::withQueryParams(['group' => $group->id])->test(ImportSchedule::class)->set('sourceText', $this->input())->call('prepare');
        $page->set('sourceText', $this->input()."\n")->call('publish')->assertHasErrors('sourceText');
        $page->call('prepare')->assertHasNoErrors();
        $blocks = $group->page_blocks;
        $blocks[1]['data']['title'] = 'Изменено другим сотрудником';
        $group->update(['page_blocks' => $blocks]);
        $page->call('publish')->assertHasErrors('sourceText');
        $this->assertSame($blocks, $group->fresh()->page_blocks);
    }

    public function test_import_is_for_group_editor_only_and_errors_never_replace_schedule(): void
    {
        $group = $this->group();
        $before = $group->page_blocks;
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/import-schedule?group='.$group->id)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'student']))->get('/admin/import-schedule?group='.$group->id)->assertForbidden();
        $this->actingAs($group->administrator);
        Livewire::withQueryParams(['group' => $group->id])->test(ImportSchedule::class)->set('sourceText', "Дата\tВремя\tТема")->call('prepare')->call('publish')->assertHasErrors('sourceText');
        $this->assertSame($before, $group->fresh()->page_blocks);
    }

    public function test_google_url_uses_exact_gid_and_disallows_external_hosts(): void
    {
        $this->assertSame('https://docs.google.com/spreadsheets/d/example/export?format=csv&gid=42', GoogleScheduleSource::csvUrl('https://docs.google.com/spreadsheets/d/example/edit#gid=42'));
        $this->assertSame('https://docs.google.com/spreadsheets/d/e/example/pub?output=csv&gid=7', GoogleScheduleSource::csvUrl('https://docs.google.com/spreadsheets/d/e/example/pubhtml?gid=7'));
        Http::preventStrayRequests();
        Http::fake(['docs.google.com/*' => Http::response($this->input(), 200, ['Content-Type' => 'text/csv'])]);
        $this->assertSame($this->input(), app(GoogleScheduleSource::class)->read('https://docs.google.com/spreadsheets/d/example/edit#gid=42'));
        $this->expectException(InvalidArgumentException::class);
        GoogleScheduleSource::csvUrl('https://docs.google.com.attacker.invalid/spreadsheets/d/example/edit');
    }

    public function test_google_login_or_external_redirect_is_not_followed(): void
    {
        Http::preventStrayRequests();
        Http::fake(['docs.google.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
        try {
            app(GoogleScheduleSource::class)->read('https://docs.google.com/spreadsheets/d/example/edit');
            $this->fail('External redirect accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('входа', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_optional_columns_can_clear_values_when_explicitly_present(): void
    {
        $parsed = ScheduleImport::parse("Дата\tВремя\tТема\tID занятия\tМодуль\n06.11.2026\t10:00–19:00\tФинансы\tone\t");
        $merged = ScheduleImport::merge($parsed, [['source_id' => 'one', 'module' => 'Старое', 'url' => 'https://example.com']]);
        $this->assertSame('', $merged['lessons'][0]['module']);
        $this->assertSame('https://example.com', $merged['lessons'][0]['url']);
        $zero = ScheduleImport::parse("Дата\tВремя\tТема\tID занятия\n06.11.2026\t10:00–19:00\tФинансы\t0");
        $this->assertSame('Модуль', ScheduleImport::merge($zero, [['source_id' => '0', 'date' => '2026-11-05', 'module' => 'Модуль']])['lessons'][0]['module']);
    }
}
