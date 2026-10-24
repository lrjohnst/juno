<?php

namespace App\Models;

use App\Enums\DispatchEventLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchEvent extends Model
{
    protected $fillable = ['label', 'description', 'happened_at'];

    protected function casts(): array
    {
        return [
            'label' => DispatchEventLabel::class,
            'happened_at' => 'datetime',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }
}
