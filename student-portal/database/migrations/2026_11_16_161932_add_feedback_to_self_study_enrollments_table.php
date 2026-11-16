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
        Schema::table('self_study_enrollments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->text('feedback_liked')->nullable();
            $table->text('feedback_improvements')->nullable();
            $table->timestamp('feedback_submitted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('self_study_enrollments', function (Blueprint $table): void {
            $table->dropColumn(['feedback_rating', 'feedback_liked', 'feedback_improvements', 'feedback_submitted_at']);
        });
    }
};
