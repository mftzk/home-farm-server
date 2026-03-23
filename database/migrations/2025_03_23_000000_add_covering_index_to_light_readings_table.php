<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('light_readings', function (Blueprint $table) {
            $table->dropIndex(['recorded_at']);
            $table->index(['recorded_at', 'lux'], 'light_readings_recorded_at_lux_index');
        });
    }

    public function down(): void
    {
        Schema::table('light_readings', function (Blueprint $table) {
            $table->dropIndex('light_readings_recorded_at_lux_index');
            $table->index('recorded_at');
        });
    }
};
