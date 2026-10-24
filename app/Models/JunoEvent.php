<?php

namespace App\Models;

use App\Enums\JunoEventLabel;
use Illuminate\Database\Eloquent\Model;

class JunoEvent extends Model
{
    protected $fillable = ['label', 'description', 'happened_at'];

    protected function casts(): array
    {
        return [
            'label' => JunoEventLabel::class,
            'happened_at' => 'datetime',
        ];
    }
}
