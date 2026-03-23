<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM light_readings'))
            ->where('Column_name', 'recorded_at')
            ->pluck('Key_name')
            ->unique();

        Schema::table('light_readings', function (Blueprint $table) use ($indexes) {
            foreach ($indexes as $name) {
                if ($name === 'PRIMARY') {
                    continue;
                }
                $table->dropIndex($name);
            }

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
