<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LightReading extends Model
{
    public $timestamps = false;

    protected $fillable = ['lux'];

    protected function casts(): array
    {
        return [
            'lux' => 'float',
            'recorded_at' => 'datetime',
        ];
    }
}
