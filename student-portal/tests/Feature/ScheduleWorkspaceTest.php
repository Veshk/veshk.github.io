<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportSchedule;
use App\Filament\Pages\Schedules;
use App\Filament\Resources\StudyGroups\Pages\EditGroupPage;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleWorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function group(): StudyGroup
    {
        return StudyGroup::factory()->create(['page_blocks' => [['type' => 'schedule', 'data' => ['block_id' => 'schedule', 'title' => 'Расписание', 'lessons' => [['source_id' => 'one', 'date' => '2026-11-06', 'time' => '10:00–12:00', 'title' => 'Исходная тема', 'teacher' => 'Лектор', 'url' => 'https://example.com/join']]]]]]);
    }

    public function test_append_preserves_existing_data_and_changing_mode_requires_new_preview(): void
    {
        $group = $this->group();
        $this->actingAs($group->administrator);
        $text = "Дата\tВремя\tТема\tID занятия\n07.11.2026\t10:00–12:00\tПеренос\tone\n08.11.2026\t10:00–12:00\tНовое занятие\ttwo";
        $page = Livewire::withQueryParams(['group' => $group->id])->test(ImportSchedule::class)->set('sourceText', $text)->call('prepare')->assertSet('preview.counts.added', 1)->assertSet('preview.counts.removed', 0)->assertSet('preview.counts.changed', 0);
        $page->set('mergeMode', 'replace')->call('publish')->assertHasErrors('sourceText');
        $this->assertCount(1, $group->fresh()->page_blocks[0]['data']['lessons']);
        $page->set('mergeMode', 'append')->call('prepare')->call('publish')->assertHasNoErrors();
        $this->assertCount(2, $group->fresh()->page_blocks[0]['data']['lessons']);
        $this->assertSame('Исходная тема', $group->fresh()->page_blocks[0]['data']['lessons'][0]['title']);
        $page->call('prepare')->assertSet('preview.counts.added', 0);
    }

    public function test_directory_allows_other_group_reading_but_rejects_writes_and_filters_owner(): void
    {
        $own = $this->group();
        $other = $this->group();
        $this->actingAs($own->administrator);
        $page = Livewire::test(Schedules::class)->assertSee($own->code)->assertSee($other->code);
        $page->set('owner', (string) $own->administrator_id)->assertSee($own->code)->assertDontSee($other->code);
        $page->call('selectGroup', $other->id)->assertSee('Только просмотр')->call('editTable')->assertForbidden();
        Livewire::test(Schedules::class)->call('selectGroup', $other->id)->call('prepare')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/schedules')->assertForbidden();
    }

    public function test_mass_edit_in_directory_round_trips_current_table_and_preserves_links(): void
    {
        $group = $this->group();
        $this->actingAs($group->administrator);
        $page = Livewire::test(Schedules::class)->call('selectGroup', $group->id)->call('editTable')->assertSet('mergeMode', 'replace')->call('prepare')->assertHasNoErrors()->assertSet('preview.counts.removed', 0)->call('publish')->assertHasNoErrors();
        $this->assertSame('https://example.com/join', $group->fresh()->page_blocks[0]['data']['lessons'][0]['url']);
        $page->set('sourceText', "Дата\tВремя\tТема\tID занятия\n08.11.2026\t10:00–12:00\tИзменено\tone")->call('prepare')->call('publish')->assertHasNoErrors();
        $this->assertSame('Изменено', $group->fresh()->page_blocks[0]['data']['lessons'][0]['title']);
    }

    public function test_group_hero_is_independent_validated_and_can_be_cleared(): void
    {
        Storage::fake('public');
        $group = $this->group();
        $other = $this->group();
        $this->actingAs($group->administrator);
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['description' => 'Приветствие моей группы', 'hero_position' => 'top', 'hero_image' => UploadedFile::fake()->image('cover.jpg', 2400, 900)])->call('save')->assertHasNoFormErrors();
        Storage::disk('public')->assertExists($group->fresh()->hero_image);
        $this->assertNull($other->fresh()->hero_image);
        $this->get('/learning/'.$group->id)->assertOk()->assertSee('Приветствие моей группы')->assertSee('has-group-cover')->assertSee('object-position:top');
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['hero_image' => UploadedFile::fake()->image('small.jpg', 300, 200)])->call('save')->assertHasFormErrors(['hero_image']);
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['hero_image' => UploadedFile::fake()->create('document.txt', 10, 'text/plain')])->call('save')->assertHasFormErrors(['hero_image']);
        Livewire::test(EditGroupPage::class, ['record' => $group->id])->fillForm(['description' => '', 'hero_image' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($group->fresh()->hero_image);
        $this->get('/learning/'.$group->id)->assertDontSee('Приветствие моей группы');
    }

    public function test_seeded_schedules_use_current_pilot_pages_and_keep_the_original_snapshot(): void
    {
        $this->seed();
        $count = StudyGroup::all()->sum(fn ($g) => collect(CoursePage::forGroup($g))->where('type', 'schedule')->sum(fn ($b) => count($b['data']['lessons'] ?? [])));
        $this->assertSame(209, $count);
        $monetka = StudyGroup::where('code', 'МОНЕТКА/49586266')->firstOrFail();
        $this->assertEmpty(collect(CoursePage::forGroup($monetka))->firstWhere('type', 'schedule')['data']['lessons']);
        $mba = StudyGroup::where('code', 'МБАстрат-7.1')->firstOrFail();
        $this->assertCount(16, collect(CoursePage::forGroup($mba))->firstWhere('type', 'schedule')['data']['lessons']);
        $this->assertSame('Вера Багрянцева', $mba->administrator->name);
        $this->assertCount(4, collect(CoursePage::forGroup($mba))->firstWhere('type', 'faq')['data']['questions']);
        $source = json_decode(file_get_contents(database_path('seeders/data/school-schedules.json')), true);
        $this->assertCount(7, $source['groups']['recuGnPizfrDCbFKH']['pending_lessons']);
        $mba->update(['description' => 'Ручная правка']);
        $this->seed();
        $this->assertSame('Ручная правка', $mba->fresh()->description);
    }
}
