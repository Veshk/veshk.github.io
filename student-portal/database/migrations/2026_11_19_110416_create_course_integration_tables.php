<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('password_setup_pending')->default(false);
        });
        Schema::create('course_registration_events', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 80);
            $table->string('transaction_id', 160);
            $table->string('payload_hash', 64);
            $table->text('payload');
            $table->string('status', 30)->default('received')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('self_study_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error', 250)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'transaction_id']);
        });
        Schema::create('portal_mails', function (Blueprint $table): void {
            $table->id();
            $table->string('deduplication_key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->text('payload');
            $table->string('status', 30)->default('pending')->index();
            $table->string('provider_id')->nullable();
            $table->string('error', 250)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('portal_password_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 20);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_password_tokens');
        Schema::dropIfExists('portal_mails');
        Schema::dropIfExists('course_registration_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_setup_pending'));
    }
};
