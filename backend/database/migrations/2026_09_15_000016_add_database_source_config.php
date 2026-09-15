<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_connections', function (Blueprint $table): void {
            $table->text('connection_config')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('source_connections', function (Blueprint $table): void {
            $table->dropColumn('connection_config');
        });
    }
};
