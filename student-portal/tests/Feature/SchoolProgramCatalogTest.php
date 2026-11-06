<?php

namespace Tests\Feature;

use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use Database\Seeders\SchoolProgramSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolProgramCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_import_updates_existing_program_without_replacing_group_or_template_and_is_repeatable(): void
    {
        $blocks = CoursePage::defaults('short');
        $existing = Program::factory()->create(['tag' => 'ОНЛ_СПО', 'title' => 'Название с номером группы', 'page_blocks' => $blocks, 'template_key' => 'short']);
        $group = StudyGroup::factory()->for($existing)->create(['title' => 'Название моей группы', 'page_blocks' => $blocks]);
        $before = $group->fresh()->getAttributes();
        $unrelated = Program::factory()->create(['title' => 'Собственная программа']);

        $this->seed(SchoolProgramSeeder::class);
        $this->seed(SchoolProgramSeeder::class);

        $this->assertSame(37, Program::whereNotNull('source_id')->count());
        $this->assertDatabaseCount('programs', 38);
        $this->assertSame('Специалист по оценке персонала', $existing->fresh()->title);
        $this->assertSame($blocks, $existing->fresh()->page_blocks);
        $this->assertSame($before, $group->fresh()->getAttributes());
        $this->assertSame('Собственная программа', $unrelated->fresh()->title);
        $this->assertSame(4, Program::whereNotNull('cover_url')->count());
        $this->assertSame(4, Program::where('program_type', 'Программа проф. переподготовки')->count());
        $this->assertSame(14, Program::where('program_type', 'Программа повышения квалификации')->count());
        $this->assertSame(23, Program::whereJsonContains('formats', 'онлайн')->count());
        $this->assertSame(0, Program::whereIn('tag', ['ЧРдир', 'ФДСтарт', 'ШколаФасилитации', 'ОНЛ_ПроизвПродукт'])->count());
        $this->assertDatabaseMissing('programs', ['title' => 'MBA General']);
    }

    public function test_matching_existing_exact_title_preserves_id_and_group_tag(): void
    {
        $existing = Program::factory()->create(['title' => 'MBA Коммерческий директор', 'tag' => 'ДЕМО_КД']);
        $group = StudyGroup::factory()->for($existing)->create();
        $code = $group->code;
        $this->seed(SchoolProgramSeeder::class);
        $this->assertSame($existing->id, Program::where('tag', 'МБАКД')->sole()->id);
        $this->assertSame($code, $group->fresh()->code);
    }

    public function test_catalog_filters_combine_type_and_format_and_staff_can_view_covers(): void
    {
        $this->seed(SchoolProgramSeeder::class);
        $old = Program::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $professional = Program::where('program_type', 'Программа проф. переподготовки')->get();
        $online = Program::where('tag', 'ОНЛ_СПО')->sole();

        Livewire::test(ListPrograms::class)->assertCanNotSeeTableRecords([$old])
            ->filterTable('program_type', 'Программа проф. переподготовки')
            ->assertCanSeeTableRecords($professional)->assertCanNotSeeTableRecords([$online])
            ->filterTable('format', 'онлайн')->assertCanNotSeeTableRecords($professional)
            ->filterTable('program_type', 'Программа повышения квалификации')->assertCanSeeTableRecords([$online]);

        $this->get('/admin/programs/'.$professional->first()->id)->assertOk()->assertSee($professional->first()->cover_url)->assertSee('диплом');
        $this->get('/admin/programs/'.$professional->first()->id.'/edit')->assertForbidden();
    }

    public function test_program_cover_is_group_default_and_custom_group_cover_takes_priority(): void
    {
        $cover = 'https://veshk.ru/media/images/oblozhka_DM_VrRi61H.original.png';
        $program = Program::factory()->create(['cover_url' => $cover]);
        $group = StudyGroup::factory()->for($program)->create();
        $enrollment = Enrollment::factory()->create(['study_group_id' => $group->id]);
        $this->actingAs($enrollment->user)->get('/learning/'.$group->id)->assertOk()->assertSee($cover);

        $group->update(['hero_image' => 'group-covers/personal.jpg']);
        $this->get('/learning/'.$group->id)->assertOk()->assertSee('/storage/group-covers/personal.jpg')->assertDontSee($cover);

        $group->update(['hero_image' => null]);
        $program->update(['cover_url' => 'https://veshk.ru.attacker.invalid/media/images/image.jpg']);
        $this->get('/learning/'.$group->id)->assertOk()->assertDontSee('attacker.invalid')->assertSee('/brand/group-placeholder.png')->assertSee('has-default-cover');
    }
}
