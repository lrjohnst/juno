<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Eén versie van een bronaantekening. Onveranderlijk.
 */
class SourceNoteVersion extends Model
{
    protected $fillable = ['content', 'tick_id'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Een versie van een aantekening is onveranderlijk; maak een nieuwe versie.');
        });
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(SourceNote::class, 'source_note_id');
    }

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }
}
