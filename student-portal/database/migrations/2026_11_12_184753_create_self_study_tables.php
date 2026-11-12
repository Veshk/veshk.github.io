<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('self_study_courses', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('kind')->index();
            $table->foreignId('administrator_id')->nullable()->constrained('users');
            $table->string('expert')->nullable();
            $table->text('description')->nullable();
            $table->string('landing_url', 2000)->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('access_days')->default(90);
            $table->unsignedInteger('price_minor')->default(0);
            $table->timestamps();
        });
        Schema::create('self_study_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('self_study_course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('module_title')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->text('video_url')->nullable();
            $table->json('materials')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
        Schema::create('course_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('self_study_course_id')->constrained();
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3)->default('RUB');
            $table->unsignedInteger('access_days');
            $table->string('status')->default('pending')->index();
            $table->string('payment_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        Schema::create('self_study_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('self_study_course_id')->constrained();
            $table->foreignId('course_order_id')->nullable()->constrained();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->json('completed_lessons')->nullable();
            $table->json('notes')->nullable();
            $table->json('history')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'self_study_course_id'], 'self_study_person_course_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_study_enrollments');
        Schema::dropIfExists('course_orders');
        Schema::dropIfExists('self_study_lessons');
        Schema::dropIfExists('self_study_courses');
    }
};
