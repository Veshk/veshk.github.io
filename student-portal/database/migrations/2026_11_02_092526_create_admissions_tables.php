<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('student');
            $table->boolean('reviews_unassigned')->default(false);
        });
        Schema::create('study_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('code')->unique();
            $table->string('format')->default('Очно');
            $table->text('description')->nullable();
            $table->foreignId('administrator_id')->constrained('users');
            $table->boolean('registration_open')->default(false);
            $table->date('starts_at');
            $table->date('graduates_at');
            $table->unsignedInteger('access_days')->default(30);
            $table->json('schedule')->nullable();
            $table->json('materials')->nullable();
            $table->timestamps();
        });
        Schema::create('enrollment_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('study_group_id')->nullable()->constrained();
            $table->string('status')->default('pending')->index();
            $table->text('message')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('send_email')->default(true);
            $table->timestamps();
        });
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('study_group_id')->constrained();
            $table->date('extended_until')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'study_group_id']);
        });
        Schema::create('mail_previews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_application_id')->unique()->constrained();
            $table->string('recipient');
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_previews');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('enrollment_applications');
        Schema::dropIfExists('study_groups');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'reviews_unassigned']));
    }
};
