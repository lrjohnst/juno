<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Het resultaat van een opdracht, één keer geschreven aan het eind. Onveranderlijk.
 */
class DispatchResult extends Model
{
    protected $fillable = ['session_id', 'output', 'exit_code', 'cost_usd', 'duration_ms', 'num_turns'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Een dispatchresultaat is onveranderlijk.');
        });
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }
}
