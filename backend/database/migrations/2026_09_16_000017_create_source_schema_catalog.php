<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_schema_tables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_connection_id')->constrained()->cascadeOnDelete();
            $table->string('table_name', 255);
            $table->string('table_type', 32);
            $table->unsignedBigInteger('estimated_rows')->nullable();
            $table->json('primary_key_columns');
            $table->json('candidate_key_columns');
            $table->timestamps();
            $table->unique(['source_connection_id', 'table_name']);
        });

        Schema::create('source_schema_columns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_schema_table_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedInteger('ordinal_position');
            $table->string('data_type', 64);
            $table->string('column_type', 255)->nullable();
            $table->boolean('is_nullable')->default(false);
            $table->boolean('is_primary_key')->default(false);
            $table->boolean('is_unique_key')->default(false);
            $table->boolean('is_candidate_primary_key')->default(false);
            $table->boolean('is_foreign_key')->default(false);
            $table->boolean('is_candidate_relation')->default(false);
            $table->string('relation_confidence', 32)->nullable();
            $table->string('referenced_table', 255)->nullable();
            $table->string('referenced_column', 255)->nullable();
            $table->json('sample_values')->nullable();
            $table->timestamps();
            $table->unique(['source_schema_table_id', 'name']);
            $table->index(['source_schema_table_id', 'ordinal_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_schema_columns');
        Schema::dropIfExists('source_schema_tables');
    }
};
