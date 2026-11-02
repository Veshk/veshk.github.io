<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->string('template_key')->default('mba');
            $table->json('page_blocks')->nullable();
        });
        Schema::table('study_groups', function (Blueprint $table): void {
            $table->string('page_template')->nullable();
            $table->json('page_blocks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('study_groups', fn (Blueprint $table) => $table->dropColumn(['page_template', 'page_blocks']));
        Schema::table('programs', fn (Blueprint $table) => $table->dropColumn(['template_key', 'page_blocks']));
    }
};
