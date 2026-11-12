<?php

namespace Tests\Feature;

use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\SelfStudyLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordedCourseImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $packageDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->packageDirectory = sys_get_temp_dir().'/recorded-import-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->packageDirectory.'/files', 0700, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->packageDirectory);
        parent::tearDown();
    }

    private function package(): array
    {
        $course = SelfStudyCourse::factory()->create(['kind' => 'recorded', 'is_published' => false]);
        $lesson = SelfStudyLesson::factory()->create([
            'self_study_course_id' => $course->id, 'video_url' => null, 'duration_minutes' => 8,
            'module_title' => null, 'description' => null, 'materials' => null, 'is_published' => false,
        ]);
        $file = 'files/'.hash('sha256', '%PDF-test-content').'.pdf';
        File::put($this->packageDirectory.'/'.$file, '%PDF-test-content');
        $expected = $lesson->fresh()->only(['title', 'module_title', 'duration_minutes', 'description', 'video_url', 'materials', 'is_published']);
        $content = array_replace($expected, [
            'title' => 'Исходные данные', 'module_title' => 'Первый модуль', 'description' => 'Описание урока',
            'video_url' => 'https://kinescope.io/importTestVideo',
            'materials' => [['title' => 'Презентация', 'filename' => 'lesson.pdf', 'file' => $file]],
        ]);
        $package = ['version' => 1, 'courses' => [[
            'slug' => $course->slug, 'expected_published' => false,
            'lessons' => [['position' => 1, 'expected' => $expected, 'content' => $content]],
        ]]];
        $path = $this->packageDirectory.'/manifest.json';
        File::put($path, json_encode($package, JSON_THROW_ON_ERROR));

        return [$course, $lesson, $path, $package];
    }

    public function test_dry_run_and_repeat_import_preserve_lesson_ids_people_and_progress(): void
    {
        [$course, $lesson, $path] = $this->package();
        $access = SelfStudyEnrollment::factory()->create([
            'self_study_course_id' => $course->id, 'completed_lessons' => [$lesson->id],
            'notes' => [$lesson->id => 'Личная заметка'],
        ]);
        $before = $access->fresh()->getAttributes();
        $people = User::count();
        $this->artisan('courses:import-content', ['manifest' => $path, '--publish' => true])->assertSuccessful();
        $this->assertFalse($course->fresh()->is_published);
        $this->assertNull($lesson->fresh()->video_url);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->artisan('courses:import-content', ['manifest' => $path, '--apply' => true, '--publish' => true])->assertSuccessful();
        $this->assertTrue($course->fresh()->is_published);
        $this->assertSame($lesson->id, $course->lessons()->sole()->id);
        $this->assertTrue($lesson->fresh()->is_published);
        $this->assertSame('Исходные данные', $lesson->fresh()->title);
        $updated = $lesson->fresh()->updated_at;
        $this->travel(1)->hour();
        $this->artisan('courses:import-content', ['manifest' => $path, '--apply' => true, '--publish' => true])->assertSuccessful();
        $this->assertEquals($updated, $lesson->fresh()->updated_at);
        $this->assertSame($before, $access->fresh()->getAttributes());
        $this->assertSame($people, User::count());
        $this->assertDatabaseCount('course_orders', 0);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs($access->user)->get(route('student.lesson.material', [$course, $lesson, 0]))
            ->assertOk()->assertDownload('lesson.pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get(route('student.lesson.material', [$course, $lesson, 0]))->assertForbidden();
        $access->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($access->user)->get(route('student.lesson.material', [$course, $lesson, 0]))->assertForbidden();
    }

    public function test_editorial_conflict_aborts_all_courses_before_copying_files(): void
    {
        [$first, $lesson, $path, $package] = $this->package();
        [$second, $edited, $otherPath, $otherPackage] = $this->package();
        $package['courses'][] = $otherPackage['courses'][0];
        File::put($path, json_encode($package, JSON_THROW_ON_ERROR));
        $edited->update(['description' => 'Правка редактора']);
        $this->artisan('courses:import-content', ['manifest' => $path, '--apply' => true, '--publish' => true])
            ->expectsOutputToContain('Editorial change detected')->assertFailed();
        $this->assertNull($lesson->fresh()->video_url);
        $this->assertFalse($first->fresh()->is_published);
        $this->assertSame('Правка редактора', $edited->fresh()->description);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_modified_or_outside_package_files_are_rejected(): void
    {
        [$course, $lesson, $path, $package] = $this->package();
        $file = $package['courses'][0]['lessons'][0]['content']['materials'][0]['file'];
        File::put($this->packageDirectory.'/'.$file, 'changed');
        $this->artisan('courses:import-content', ['manifest' => $path, '--apply' => true])->assertFailed();
        $this->assertNull($lesson->fresh()->video_url);
        File::delete($this->packageDirectory.'/'.$file);
        $outside = $this->packageDirectory.'/outside.pdf';
        File::put($outside, '%PDF-test-content');
        symlink($outside, $this->packageDirectory.'/'.$file);
        $this->artisan('courses:import-content', ['manifest' => $path, '--apply' => true])->assertFailed();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_only_assigned_staff_can_download_draft_materials(): void
    {
        [$course, $lesson, $path] = $this->package();
        $this->artisan('courses:import-content', ['manifest' => $path, '--apply' => true])->assertSuccessful();
        $this->actingAs($course->administrator)->get(route('student.lesson.material', [$course, $lesson, 0]))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('student.lesson.material', [$course, $lesson, 0]))->assertForbidden();
        $access = SelfStudyEnrollment::factory()->create(['self_study_course_id' => $course->id]);
        $this->actingAs($access->user)->get(route('student.lesson.material', [$course, $lesson, 0]))->assertForbidden();
        $course->update(['is_published' => true]);
        $this->get(route('student.lesson.material', [$course, $lesson, 0]))->assertNotFound();
    }
}
