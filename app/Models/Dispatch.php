<?php

namespace App\Models;

use App\Enums\DispatchEventLabel;
use App\Exceptions\InvalidDispatchTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Eén Claude Code-opdracht van de kernel (of later van Lucas). De status staat niet hier maar in
 * dispatch_events; het resultaat in dispatch_results.
 */
class Dispatch extends Model
{
    public const PERMISSION_MODES = ['bypassPermissions'];

    protected $fillable = ['tick_id', 'prompt', 'permission_mode'];

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DispatchEvent::class);
    }

    public function latestEvent(): HasOne
    {
        return $this->hasOne(DispatchEvent::class)->ofMany(['happened_at' => 'max', 'id' => 'max']);
    }

    public function result(): HasOne
    {
        return $this->hasOne(DispatchResult::class);
    }

    public function getStatus(): ?DispatchEvent
    {
        return $this->events()->orderByDesc('happened_at')->orderByDesc('id')->first();
    }

    /**
     * Legt een nieuw event vast: ['label' => ..., 'description' => ..., 'happened_at' => ...].
     *
     * @throws InvalidDispatchTransition
     */
    public function setStatus(array $event): DispatchEvent
    {
        $label = $event['label'] instanceof DispatchEventLabel
            ? $event['label']
            : DispatchEventLabel::from($event['label']);
        $happenedAt = isset($event['happened_at'])
            ? Carbon::parse($event['happened_at'])->setTimezone(config('app.timezone'))
            : now();
        $current = $this->getStatus();

        if (! $label->canFollow($current?->label)) {
            throw new InvalidDispatchTransition(
                "Dispatch {$this->id}: {$label->value} kan niet volgen op ".($current?->label->value ?? 'niets').'.'
            );
        }

        if ($current && $happenedAt->lt($current->happened_at)) {
            throw new InvalidDispatchTransition(
                "Dispatch {$this->id}: {$label->value} kan niet vóór {$current->label->value} gebeurd zijn."
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
        return in_array($this->getStatus()?->label, DispatchEventLabel::open(), true);
    }

    public function scopeWhereStatus(Builder $query, DispatchEventLabel ...$labels): void
    {
        $query->whereHas('latestEvent', fn (Builder $event) => $event->whereIn('label', $labels));
    }

    /**
     * Geeft opdrachten op die na dispatch_timeout + 5 minuten nog op Started staan: de kernel stopt
     * ze zelf na dispatch_timeout, dus dan is de kernel waarschijnlijk gecrasht.
     */
    public static function abandonStale(): int
    {
        $minutes = (int) config('juno.dispatch_timeout') + 5;
        $abandoned = 0;

        foreach (static::whereStatus(DispatchEventLabel::Started)->get() as $dispatch) {
            if ($dispatch->getStatus()->happened_at->gt(now()->subMinutes($minutes))) {
                continue;
            }

            $dispatch->setStatus([
                'label' => DispatchEventLabel::Abandoned,
                'description' => "Geen afsluiting binnen {$minutes} minuten na Started.",
            ]);
            $abandoned++;
        }

        return $abandoned;
    }
}
