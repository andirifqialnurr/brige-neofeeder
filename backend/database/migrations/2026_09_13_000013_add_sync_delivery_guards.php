<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->timestamp('sync_started_at')->nullable();
        });
        Schema::table('sync_attempts', function (Blueprint $table): void {
            $table->char('idempotency_key', 64)->nullable()->unique();
            $table->char('approval_hash', 64)->nullable();
            $table->foreignUuid('retry_of')->nullable()->unique()->constrained('sync_attempts')->nullOnDelete();
            $table->boolean('retry_safe')->default(false);
            $table->unsignedInteger('execution_count')->default(0);
            $table->timestamp('request_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sync_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('retry_of');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'approval_hash', 'retry_safe', 'execution_count', 'request_started_at', 'completed_at']);
        });
        Schema::table('import_batches', fn (Blueprint $table) => $table->dropColumn('sync_started_at'));
    }
};
