<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eén config-waarde; `key` is de volledige config()-sleutel, bijv. 'juno.tick_interval'.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
