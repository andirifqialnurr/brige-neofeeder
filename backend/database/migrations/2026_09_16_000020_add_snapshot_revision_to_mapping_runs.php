<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_connections', function (Blueprint $table): void {
            $table->unsignedInteger('snapshot_version')->default(1);
        });

        Schema::table('mapping_runs', function (Blueprint $table): void {
            $table->unsignedInteger('source_snapshot_version')->default(1);
            $table->dropUnique('mapping_run_source_version_unique');
            $table->unique(
                ['source_connection_id', 'mapping_profile_version_id', 'source_snapshot_version'],
                'mapping_run_source_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mapping_runs', function (Blueprint $table): void {
            $table->dropUnique('mapping_run_source_version_unique');
            $table->dropColumn('source_snapshot_version');
            $table->unique(
                ['source_connection_id', 'mapping_profile_version_id'],
                'mapping_run_source_version_unique'
            );
        });

        Schema::table('source_connections', function (Blueprint $table): void {
            $table->dropColumn('snapshot_version');
        });
    }
};
