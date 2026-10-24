<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén regel in het journal van Juno. Het journal volgt níet de branch: een fout uit een
 * teruggedraaide branch moet in het geheugen blijven.
 */
class JournalEntry extends Model
{
    protected $fillable = ['content'];

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }

    /**
     * De entries van de chronologisch laatste journal_window ticks (op id, over alle branches
     * heen), op volgorde van tick en daarbinnen van entry.
     *
     * @return Collection<int, self>
     */
    public static function window(): Collection
    {
        $tickIds = Tick::latest('id')->limit(max(1, (int) config('juno.journal_window')))->pluck('id');

        return static::whereIn('tick_id', $tickIds)->orderBy('tick_id')->orderBy('id')->get();
    }
}
