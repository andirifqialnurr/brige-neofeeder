<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_connections', function (Blueprint $table): void {
            $table->string('schema_discovery_status', 24)->default('idle');
            $table->timestamp('schema_discovery_started_at')->nullable();
            $table->timestamp('schema_discovered_at')->nullable();
            $table->text('schema_discovery_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('source_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'schema_discovery_status',
                'schema_discovery_started_at',
                'schema_discovered_at',
                'schema_discovery_error',
            ]);
        });
    }
};
