<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Eén versie van een document. Onveranderlijk: een wijziging is altijd een nieuwe versie.
 */
class DocumentVersion extends Model
{
    protected $fillable = ['tick_id', 'content'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Een documentversie is onveranderlijk; maak een nieuwe versie.');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }
}
