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
        'lux_on_below',
        'lux_off_above',
        'last_auto_state',
    ];

    protected function casts(): array
    {
        return [
            'auto_enabled' => 'boolean',
            'lux_on_below' => 'float',
            'lux_off_above' => 'float',
            'last_auto_state' => 'boolean',
        ];
    }
}
