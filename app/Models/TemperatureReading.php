<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemperatureReading extends Model
{
    public $timestamps = false;

    protected $fillable = ['temperature', 'humidity'];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'humidity' => 'float',
            'recorded_at' => 'datetime',
        ];
    }
}
