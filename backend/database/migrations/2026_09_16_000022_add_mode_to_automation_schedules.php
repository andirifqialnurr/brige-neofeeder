<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_schedules', function (Blueprint $table): void {
            $table->string('mode', 16)->default('full')->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('automation_schedules', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });
    }
};
