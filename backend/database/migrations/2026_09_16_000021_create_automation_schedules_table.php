<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('mapping_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('last_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('name');
            $table->string('frequency', 16);
            $table->boolean('is_active')->default(true)->index();
            $table->string('status', 24)->default('idle')->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['source_connection_id', 'mapping_profile_id'], 'automation_schedule_source_profile_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_schedules');
    }
};
