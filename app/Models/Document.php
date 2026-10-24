<?php

namespace App\Models;

use App\Exceptions\InvalidDocumentContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Een document van Juno, zoals mission.md of state.json. De inhoud staat in versies; elke versie
 * hangt aan de tick die hem schreef.
 *
 * follows_rollback = true: de stand bij een tick is de nieuwste versie van een tick op zijn route
 * terug (getAncestors), dus een rollback draait het document mee terug.
 * follows_rollback = false: altijd de nieuwste versie, over alle branches heen.
 */
class Document extends Model
{
    protected $fillable = ['name', 'follows_rollback'];

    protected function casts(): array
    {
        return [
            'follows_rollback' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * De versie die bij $tick geldt, of null als er op die route nog geen versie is.
     */
    public function getVersion(Tick $tick): ?DocumentVersion
    {
        $versions = $this->versions()->latest('id');

        if ($this->follows_rollback) {
            $versions->whereIn('tick_id', $tick->getAncestors()->modelKeys());
        }

        return $versions->first();
    }

    public function getContent(Tick $tick): ?string
    {
        return $this->getVersion($tick)?->content;
    }

    /**
     * Legt een nieuwe versie vast, tenzij de inhoud gelijk is aan de stand bij $tick.
     *
     * @throws InvalidDocumentContent
     */
    public function setContent(string $content, Tick $tick): DocumentVersion
    {
        if (Str::endsWith($this->name, '.json') && ! json_validate($content)) {
            throw new InvalidDocumentContent("{$this->name}: geen geldige JSON.");
        }

        $current = $this->getVersion($tick);

        if ($current && $current->content === $content) {
            return $current;
        }

        return $this->versions()->create([
            'tick_id' => $tick->id,
            'content' => $content,
        ]);
    }
}
