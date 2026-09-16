<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_connections', function (Blueprint $table): void {
            $table->string('snapshot_status', 24)->default('idle');
            $table->timestamp('snapshot_started_at')->nullable();
            $table->timestamp('snapshot_refreshed_at')->nullable();
            $table->text('snapshot_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('source_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'snapshot_status',
                'snapshot_started_at',
                'snapshot_refreshed_at',
                'snapshot_error',
            ]);
        });
    }
};
