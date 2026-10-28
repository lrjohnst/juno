<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een bericht tussen teamleden. tick_id is de tick waarin het verstuurd is; null bij een bericht van Lucas.
 */
class AgentEmail extends Model
{
    protected $fillable = ['tick_id', 'from_agent_id', 'to_agent_id', 'body'];

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'from_agent_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'to_agent_id');
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'tick_id' => $this->tick_id,
            'from' => $this->from->name,
            'to' => $this->to->name,
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
