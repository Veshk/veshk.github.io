<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\StudyGroup;
use App\Models\User;
use App\Support\CoursePage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedBlocksTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function source(): StudyGroup
    {
        return StudyGroup::factory()->create(['page_blocks' => [['type' => 'recordings', 'data' => ['block_id' => 'competencies', 'title' => 'Оценка компетенций', 'visible' => true, 'videos' => [['module' => 'Оценка', 'title' => 'Исходная запись', 'url' => 'https://example.com/video']]]]]]);
    }

    private function alias(StudyGroup $source): array
    {
        return [['type' => 'alias', 'data' => ['block_id' => 'linked', 'title' => 'Общие записи', 'visible' => true, 'source_group' => (string) $source->id, 'source_block' => 'competencies']]];
    }

    public function test_alias_shows_live_source_without_granting_access_to_entire_source_group(): void
    {
        $source = $this->source();
        $target = StudyGroup::factory()->create(['page_blocks' => $this->alias($source)]);
        $student = Enrollment::factory()->create(['study_group_id' => $target->id])->user;
        $this->actingAs($student)->get('/learning/'.$target->id)->assertOk()->assertSee('Исходная запись');
        $this->get('/learning/'.$source->id)->assertForbidden();
        $blocks = $source->page_blocks;
        $blocks[0]['data']['videos'][0]['title'] = 'Обновлённая запись';
        $source->update(['page_blocks' => $blocks]);
        $this->get('/learning/'.$target->id)->assertSee('Обновлённая запись')->assertDontSee('Исходная запись');
        $this->assertSame($this->alias($source), $target->fresh()->page_blocks);
        $this->assertSame([$target->id], array_values(array_map(fn ($g) => $g->id, CoursePage::references($source->id, 'competencies'))));
        $blocks[0]['data']['visible'] = false;
        $source->update(['page_blocks' => $blocks]);
        $this->get('/learning/'.$target->id)->assertSee('Содержимое временно недоступно')->assertDontSee('Обновлённая запись');
    }

    public function test_references_stay_stable_after_source_reorder_and_missing_source_is_handled(): void
    {
        $source = $this->source();
        $target = StudyGroup::factory()->create(['page_blocks' => $this->alias($source)]);
        $source->update(['page_blocks' => array_merge([['type' => 'info', 'data' => ['block_id' => 'new', 'title' => 'Другой блок']]], $source->page_blocks)]);
        $this->assertSame('Исходная запись', CoursePage::display($target)[0]['data']['videos'][0]['title']);
        $source->update(['page_blocks' => []]);
        $this->assertSame('info', CoursePage::display($target)[0]['type']);
    }

    public function test_admin_cannot_create_alias_to_unmanaged_source_and_existing_approved_link_is_preserved(): void
    {
        $source = $this->source();
        $target = StudyGroup::factory()->create(['page_blocks' => $this->alias($source)]);
        $this->actingAs($target->administrator);
        CoursePage::validateAliases($target->page_blocks, $target, $target->administrator);
        $this->expectException(ValidationException::class);
        CoursePage::validateAliases($this->alias($source), StudyGroup::factory()->create(['administrator_id' => $target->administrator_id]), $target->administrator);
    }

    public function test_self_reference_is_rejected(): void
    {
        $source = $this->source();
        $this->expectException(ValidationException::class);
        CoursePage::validateAliases($this->alias($source), $source, User::factory()->create(['role' => 'senior']));
    }
}
