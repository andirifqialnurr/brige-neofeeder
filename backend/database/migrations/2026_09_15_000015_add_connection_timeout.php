<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neofeeder_connections', fn (Blueprint $table) => $table->unsignedInteger('timeout_ms')->default(30000));
    }

    public function down(): void
    {
        Schema::table('neofeeder_connections', fn (Blueprint $table) => $table->dropColumn('timeout_ms'));
    }
};
