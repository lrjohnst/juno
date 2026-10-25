<?php

namespace App\Models;

use App\Enums\ApiTokenEventLabel;
use Illuminate\Database\Eloquent\Model;

class ApiTokenEvent extends Model
{
    protected $fillable = ['label', 'description', 'happened_at'];

    protected function casts(): array
    {
        return [
            'label' => ApiTokenEventLabel::class,
            'happened_at' => 'datetime',
        ];
    }
}
