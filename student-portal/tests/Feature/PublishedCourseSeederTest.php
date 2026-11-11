<?php

namespace Tests\Feature;

use App\Models\AcademicAdvisor;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use Database\Seeders\PublishedCourseSeeder;
use Database\Seeders\SchoolProgramSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class PublishedCourseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_imports_only_two_approved_groups_without_demo_students_or_credentials(): void
    {
        $senior = User::factory()->create(['role' => 'senior', 'email' => 'rita@veshk.ru']);
        $this->seed(SchoolProgramSeeder::class);
        $programCount = Program::count();
        $this->seed(PublishedCourseSeeder::class);

        $this->assertEqualsCanonicalizing(['ОНЛ_СПО-8', 'КД-26'], StudyGroup::pluck('code')->all());
        $this->assertDatabaseCount('programs', $programCount);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('enrollment_applications', 0);
        $this->assertDatabaseCount('academic_advisors', 1);
        $administrator = User::where('email', 'dasha.veshk@gmail.com')->sole();
        $this->assertSame('Дарья Маврина', $administrator->name);
        $this->assertSame('admin', $administrator->role);
        $this->assertFalse($administrator->reviews_unassigned);
        $this->assertFalse(Hash::check('VeshDemo2026!', $administrator->password));
        $this->assertNull($administrator->email_verified_at);

        $spo = StudyGroup::where('code', 'ОНЛ_СПО-8')->sole();
        $kd = StudyGroup::where('code', 'КД-26')->sole();
        $this->assertCount(15, collect($spo->page_blocks)->firstWhere('type', 'schedule')['data']['lessons']);
        $this->assertCount(32, collect($kd->page_blocks)->firstWhere('type', 'schedule')['data']['lessons']);
        $this->assertSame('2026-12-09', $kd->closesAt()->toDateString());
        $this->assertSame('rublevskaya.julia@veshk.ru', $kd->academicAdvisor->email);
        $this->assertNull($spo->academic_advisor_id);
        $this->assertSame($administrator->id, $spo->administrator_id);
        $this->assertSame($administrator->id, $kd->administrator_id);
        $this->assertTrue($spo->registration_open);
        $this->assertFalse($kd->registration_open);
        $this->actingAs($senior)->get(route('student.group', $spo))->assertOk()->assertSee('Домашние задания')->assertSee('Дарья Маврина');
        $this->get(route('student.group', $kd))->assertOk()->assertSee('mailto:rublevskaya.julia@veshk.ru', false)->assertSee('Фотографии с выпускного');
    }

    public function test_rerun_preserves_existing_content_profiles_and_passwords(): void
    {
        $this->seed(PublishedCourseSeeder::class);
        $group = StudyGroup::where('code', 'КД-26')->sole();
        $group->update(['description' => 'Новая правка', 'page_blocks' => []]);
        $group->program->update(['title' => 'Новое название']);
        $group->academicAdvisor->update(['email' => 'updated@example.com']);
        $administrator = $group->administrator;
        $administrator->forceFill(['name' => 'Изменённое имя', 'contact_text' => 'Новые контакты', 'profile_photo' => 'staff-photos/new.png'])->save();
        $password = $administrator->password;

        $this->seed(PublishedCourseSeeder::class);

        $this->assertDatabaseCount('study_groups', 2);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('academic_advisors', 1);
        $this->assertSame('Новая правка', $group->fresh()->description);
        $this->assertSame([], $group->fresh()->page_blocks);
        $this->assertSame('Новое название', $group->program->fresh()->title);
        $this->assertSame('updated@example.com', AcademicAdvisor::sole()->email);
        $this->assertSame($password, $administrator->fresh()->password);
        $this->assertSame('Изменённое имя', $administrator->fresh()->name);
        $this->assertSame('Новые контакты', $administrator->fresh()->contact_text);
        $this->assertSame('staff-photos/new.png', $administrator->fresh()->profile_photo);
    }

    public function test_email_conflict_aborts_without_promoting_a_student_or_partial_import(): void
    {
        $student = User::factory()->create(['email' => 'dasha.veshk@gmail.com']);
        try {
            $this->seed(PublishedCourseSeeder::class);
            $this->fail('Expected conflicting student email to stop the import.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Перенос отменён', $exception->getMessage());
        }

        $this->assertSame('student', $student->fresh()->role);
        $this->assertDatabaseCount('study_groups', 0);
        $this->assertDatabaseCount('academic_advisors', 0);
        $this->assertDatabaseCount('programs', 0);
    }
}
