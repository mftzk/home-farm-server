<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temperature_readings')) {
            return;
        }

        Schema::create('temperature_readings', function (Blueprint $table) {
            $table->increments('id');
            $table->float('temperature');
            $table->float('humidity');
            $table->dateTime('recorded_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_readings');
    }
};
