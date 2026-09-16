<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_schedules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('error_rate_threshold')->default(50)->after('mode');
            $table->boolean('alert_active')->default(false)->index();
            $table->timestamp('alert_triggered_at')->nullable();
        });

        Schema::create('automation_schedule_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->index();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('last_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['automation_schedule_id', 'created_at'], 'automation_schedule_runs_schedule_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_schedule_runs');

        Schema::table('automation_schedules', function (Blueprint $table): void {
            $table->dropColumn(['error_rate_threshold', 'alert_active', 'alert_triggered_at']);
        });
    }
};
