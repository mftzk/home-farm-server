<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelayAutoConfig extends Model
{
    protected $primaryKey = 'relay_id';

    public $incrementing = false;

    protected $fillable = [
        'relay_id',
        'auto_enabled',
        'sensor_type',
        'condition',
        'threshold_on',
        'threshold_off',
        'last_auto_state',
    ];

    protected function casts(): array
    {
        return [
            'auto_enabled' => 'boolean',
            'threshold_on' => 'float',
            'threshold_off' => 'float',
            'last_auto_state' => 'boolean',
        ];
    }
}
