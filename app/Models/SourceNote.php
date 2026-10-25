<?php

namespace App\Models;

use App\Enums\SourceNoteKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Een samenvatting, aantekening of citaat bij een bron, voor één TF-onderwerp (bijv. tf_market_efficiency).
 * De inhoud staat in versies; een wijziging is altijd een nieuwe versie.
 */
class SourceNote extends Model
{
    protected $fillable = ['topic', 'kind'];

    protected function casts(): array
    {
        return [
            'kind' => SourceNoteKind::class,
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SourceNoteVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(SourceNoteVersion::class)->latestOfMany();
    }

    public function getContent(): ?string
    {
        return $this->versions()->latest('id')->first()?->content;
    }

    /**
     * Nieuwe versie, tenzij de inhoud gelijk is aan de laatste (dan komt die terug).
     */
    public function setContent(string $content, ?Tick $tick = null): SourceNoteVersion
    {
        $current = $this->versions()->latest('id')->first();

        if ($current && $current->content === $content) {
            return $current;
        }

        return $this->versions()->create(['content' => $content, 'tick_id' => $tick?->id]);
    }
}
