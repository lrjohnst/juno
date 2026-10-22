<?php

namespace App\Models;

use App\Enums\TickEventLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TickEvent extends Model
{
    protected $fillable = ['label', 'description', 'happened_at'];

    protected function casts(): array
    {
        return [
            'label' => TickEventLabel::class,
            'happened_at' => 'datetime',
        ];
    }

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }
}
