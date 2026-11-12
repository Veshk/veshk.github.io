<?php

namespace Database\Seeders;

use App\Models\SelfStudyCourse;
use App\Models\User;
use App\Services\SelfStudyAccess;
use Illuminate\Database\Seeder;

class SelfStudyDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Демо курсов разрешено только в local/testing.');
        }
        $this->call(SelfStudyCatalogSeeder::class);
        $admin = User::where('email', 'admin@vesh.test')->firstOrFail();
        $student = User::where('email', 'student@vesh.test')->firstOrFail();
        foreach (SelfStudyCourse::all() as $course) {
            $course->update(['administrator_id' => $admin->id, 'is_published' => true]);
            if ($course->kind === 'free') {
                app(SelfStudyAccess::class)->grantFree($course, $student, $admin);
            }
        }
        $course = SelfStudyCourse::where('slug', 'dashboards')->firstOrFail();
        if (! $student->selfStudyEnrollments()->where('self_study_course_id', $course->id)->exists()) {
            $order = app(SelfStudyAccess::class)->createOrder($course, $student, $admin);
            app(SelfStudyAccess::class)->confirmPayment($order, $admin, 'DEMO-ONLY-'.$order->id, $order->amount_minor, now()->subDay()->toIso8601String());
        }
    }
}
