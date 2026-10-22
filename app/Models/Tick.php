<?php

namespace App\Models;

use App\Enums\TickEventLabel;
use App\Exceptions\InvalidTickTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Eén tick van de agent. De status staat niet hier maar in tick_events:
 * het laatste event (op happened_at, daarna id) is de status.
 */
class Tick extends Model
{
    public function events(): HasMany
    {
        return $this->hasMany(TickEvent::class);
    }

    public function latestEvent(): HasOne
    {
        return $this->hasOne(TickEvent::class)->ofMany(['happened_at' => 'max', 'id' => 'max']);
    }

    public function getStatus(): ?TickEvent
    {
        return $this->events()->orderByDesc('happened_at')->orderByDesc('id')->first();
    }

    /**
     * Legt een nieuw event vast: ['label' => ..., 'description' => ..., 'happened_at' => ...].
     * Alleen label is verplicht; happened_at is standaard nu.
     *
     * @throws InvalidTickTransition
     */
    public function set_status(array $event): TickEvent
    {
        $label = $event['label'] instanceof TickEventLabel
            ? $event['label']
            : TickEventLabel::from($event['label']);
        $happenedAt = isset($event['happened_at'])
            ? Carbon::parse($event['happened_at'])->setTimezone(config('app.timezone'))
            : now();
        $current = $this->getStatus();

        if (! $label->canFollow($current?->label)) {
            throw new InvalidTickTransition(
                "Tick {$this->id}: {$label->value} kan niet volgen op ".($current?->label->value ?? 'niets').'.'
            );
        }

        // Anders is het nieuwe event niet het laatste, en klopt getStatus() niet meer.
        if ($current && $happenedAt->lt($current->happened_at)) {
            throw new InvalidTickTransition(
                "Tick {$this->id}: {$label->value} kan niet vóór {$current->label->value} gebeurd zijn."
            );
        }

        return $this->events()->create([
            'label' => $label,
            'description' => $event['description'] ?? null,
            'happened_at' => $happenedAt,
        ]);
    }

    public function isOpen(): bool
    {
        return in_array($this->getStatus()?->label, TickEventLabel::open(), true);
    }

    public function scopeWhereStatus(Builder $query, TickEventLabel ...$labels): void
    {
        $query->whereHas('latestEvent', fn (Builder $event) => $event->whereIn('label', $labels));
    }

    /**
     * Zet een nieuwe tick klaar, maar alleen als er geen tick open staat.
     */
    public static function schedule(): ?self
    {
        return DB::transaction(function () {
            if (static::whereStatus(...TickEventLabel::open())->lockForUpdate()->exists()) {
                return null;
            }

            $tick = static::create();
            $tick->set_status([
                'label' => TickEventLabel::Scheduled,
                'description' => 'Klaargezet door de scheduler.',
            ]);

            return $tick;
        });
    }

    /**
     * Geeft de oudste klaargezette tick aan de aanroeper, of null als er niets klaarstaat.
     */
    public static function claimNext(): ?self
    {
        return DB::transaction(function () {
            $tick = static::whereStatus(TickEventLabel::Scheduled)->oldest('id')->lockForUpdate()->first();

            if (! $tick) {
                return null;
            }

            try {
                $tick->set_status([
                    'label' => TickEventLabel::Claimed,
                    'description' => 'Geclaimd door tick.php.',
                ]);
            } catch (InvalidTickTransition) {
                return null; // Net door een andere aanroep geclaimd.
            }

            return $tick;
        });
    }

    /**
     * Geeft geclaimde ticks zonder afsluiting na tick_abandon_after minuten op.
     */
    public static function abandonStale(): int
    {
        $minutes = (int) config('juno.tick_abandon_after');
        $abandoned = 0;

        foreach (static::whereStatus(TickEventLabel::Claimed)->get() as $tick) {
            if ($tick->getStatus()->happened_at->gt(now()->subMinutes($minutes))) {
                continue;
            }

            $tick->set_status([
                'label' => TickEventLabel::Abandoned,
                'description' => "Geen Finished of Failed binnen {$minutes} minuten na Claimed.",
            ]);
            $abandoned++;
        }

        return $abandoned;
    }
}
