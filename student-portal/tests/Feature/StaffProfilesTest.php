<?php

namespace Tests\Feature;

use App\Filament\Pages\StaffProfiles;
use App\Models\Enrollment;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StaffProfilesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_uploads_photo_and_group_uses_current_administrator_profile(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $photo = UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X/WQAAAAASUVORK5CYII='));
        Livewire::test(StaffProfiles::class)->set('contactText', 'Отвечаю по будням')->set('contactUrl', 'https://example.com/contact')->set('photo', $photo)->call('save')->assertHasNoErrors();
        $admin->refresh();
        Storage::disk('public')->assertExists($admin->profile_photo);
        $group = StudyGroup::factory()->create(['administrator_id' => $admin->id, 'page_blocks' => [['type' => 'contacts', 'data' => ['title' => 'Контакты', 'visible' => true]]]]);
        $student = Enrollment::factory()->create(['study_group_id' => $group->id])->user;
        $this->actingAs($student)->get('/learning/'.$group->id)->assertOk()->assertSee('Отвечаю по будням')->assertSee($admin->profile_photo)->assertSee('https://example.com/contact');
        $other = User::factory()->create(['role' => 'admin', 'contact_text' => 'Новый администратор', 'contact_url' => 'https://example.com/new']);
        $group->update(['administrator_id' => $other->id]);
        $this->get('/learning/'.$group->id)->assertSee('Новый администратор')->assertSee('https://example.com/new')->assertDontSee($admin->profile_photo);
    }

    public function test_ordinary_admin_cannot_change_other_profile_even_by_tampering_with_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Livewire::test(StaffProfiles::class)->set('selectedId', $other->id)->call('save')->assertForbidden();
        $this->assertNull($other->fresh()->contact_text);
        $this->actingAs(User::factory()->create())->get('/admin/staff-profiles')->assertForbidden();
    }

    public function test_senior_can_edit_staff_but_not_student_and_rejects_non_image_or_unsafe_url(): void
    {
        $senior = User::factory()->create(['role' => 'senior']);
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $this->actingAs($senior);
        Livewire::test(StaffProfiles::class)->call('selectPerson', $admin->id)->set('contactText', 'Общий контакт')->call('save')->assertHasNoErrors();
        $this->assertSame('Общий контакт', $admin->fresh()->contact_text);
        Livewire::test(StaffProfiles::class)->call('selectPerson', $student->id)->assertNotFound();
        Livewire::test(StaffProfiles::class)->set('contactUrl', 'javascript:alert(1)')->call('save')->assertHasErrors('contactUrl');
        Livewire::test(StaffProfiles::class)->set('photo', UploadedFile::fake()->create('document.txt', 10, 'text/plain'))->call('save')->assertHasErrors('photo');
    }

    public function test_directory_includes_staff_without_groups_and_only_senior_can_grant_review_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $senior = User::factory()->create(['role' => 'senior']);
        StudyGroup::factory()->create(['administrator_id' => $other->id]);
        $this->actingAs($admin);
        Livewire::test(StaffProfiles::class)->assertCanSeeTableRecords([$admin, $other, $senior])->filterTable('has_groups', false)->assertCanSeeTableRecords([$admin, $senior])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(StaffProfiles::class)->set('reviewsUnassigned', true)->call('save')->assertForbidden();
        $this->assertFalse($admin->fresh()->reviews_unassigned);
        $this->actingAs($senior);
        Livewire::test(StaffProfiles::class)->call('selectPerson', $admin->id)->set('reviewsUnassigned', true)->call('save')->assertHasNoErrors();
        $this->assertTrue($admin->fresh()->reviews_unassigned);
        Livewire::test(StaffProfiles::class)->call('selectPerson', $admin->id)->set('reviewsUnassigned', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($admin->fresh()->reviews_unassigned);
    }

    public function test_shared_login_and_registration_form_order_and_start_date(): void
    {
        $group = StudyGroup::factory()->create(['title' => 'Тестовая программа', 'registration_open' => true, 'starts_at' => '2026-11-06']);
        $this->get('/login')->assertOk()->assertSee('Вход в личный кабинет')->assertSee('Зарегистрироваться');
        $this->get('/admin/login')->assertRedirect('/login');
        $this->get('/register')->assertOk()->assertSee('Тестовая программа — старт 06.11.2026')->assertSeeInOrder(['Электронная почта', 'Повторите пароль', 'Комментарий']);
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'TestPass2026!']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'TestPass2026!'])->assertRedirect('/admin');
    }
}
