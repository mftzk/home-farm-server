<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relay_auto_configs', function (Blueprint $table) {
            $table->tinyInteger('relay_id')->unsigned()->primary();
            $table->boolean('auto_enabled')->default(false);
            $table->enum('sensor_type', ['light', 'temperature'])->default('light');
            $table->enum('condition', ['below', 'above'])->default('below');
            $table->float('threshold_on')->default(50);
            $table->float('threshold_off')->default(100);
            $table->boolean('last_auto_state')->nullable();
            $table->timestamps();
        });

        for ($i = 0; $i < 4; $i++) {
            DB::table('relay_auto_configs')->insert([
                'relay_id' => $i,
                'auto_enabled' => false,
                'sensor_type' => 'light',
                'condition' => 'below',
                'threshold_on' => 50,
                'threshold_off' => 100,
                'last_auto_state' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_auto_configs');
    }
};
