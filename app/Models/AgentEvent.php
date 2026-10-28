<?php

namespace App\Models;

use App\Enums\AgentEventLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEvent extends Model
{
    protected $fillable = ['tick_id', 'label', 'description', 'happened_at'];

    protected function casts(): array
    {
        return [
            'label' => AgentEventLabel::class,
            'happened_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }
}
