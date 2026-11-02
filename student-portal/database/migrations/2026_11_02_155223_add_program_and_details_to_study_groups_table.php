<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_groups', function (Blueprint $table): void {
            $table->foreignId('program_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('number', 60)->nullable();
            $table->string('group_type')->nullable();
            $table->string('status')->nullable();
            $table->text('chat_url')->nullable();
            $table->date('extended_until')->nullable();
            $table->text('admin_notes')->nullable();
            $table->boolean('setup_completed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('study_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn(['number', 'group_type', 'status', 'chat_url', 'extended_until', 'admin_notes', 'setup_completed']);
        });
    }
};
