<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->index();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('operation')->default('insert')->index();
            $table->string('natural_key')->nullable()->index();
            $table->json('raw_row')->nullable();
            $table->json('normalized_row')->nullable();
            $table->json('validation_result')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_records');
    }
};
