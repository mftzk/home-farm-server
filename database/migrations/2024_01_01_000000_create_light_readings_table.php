<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('light_readings')) {
            return;
        }

        Schema::create('light_readings', function (Blueprint $table) {
            $table->increments('id');
            $table->float('lux');
            $table->dateTime('recorded_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('light_readings');
    }
};
