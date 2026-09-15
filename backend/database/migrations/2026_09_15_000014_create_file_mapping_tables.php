<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('file');
            $table->string('name');
            $table->string('sheet_name')->nullable();
            $table->string('sha256', 64);
            $table->json('headers');
            $table->json('snapshot');
            $table->unsignedInteger('row_count');
            $table->timestamps();
        });
        Schema::create('mapping_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('mapping_profile_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mapping_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('rules');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['mapping_profile_id', 'version']);
        });
        Schema::create('mapping_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('mapping_profile_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('preview_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['source_connection_id', 'mapping_profile_version_id'], 'mapping_run_source_version_unique');
        });
        Schema::table('staging_records', fn (Blueprint $table) => $table->json('source_lineage')->nullable());
    }

    public function down(): void
    {
        Schema::table('staging_records', fn (Blueprint $table) => $table->dropColumn('source_lineage'));
        Schema::dropIfExists('mapping_runs');
        Schema::dropIfExists('mapping_profile_versions');
        Schema::dropIfExists('mapping_profiles');
        Schema::dropIfExists('source_connections');
    }
};
