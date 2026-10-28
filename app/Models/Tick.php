<?php

namespace App\Models;

use App\Enums\TickEventLabel;
use App\Enums\TickRole;
use App\Exceptions\InvalidTickTransition;
use App\Juno;
use App\Support\MailLucas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Eén tick van de agent. De status staat niet hier maar in tick_events:
 * het laatste event (op happened_at, daarna id) is de status.
 *
 * Elke tick bouwt voort op een parent, zoals een git-commit. Na een rollback krijgt de
 * nieuwe tick een oudere tick als parent; zo ontstaat een nieuwe branch.
 */
class Tick extends Model
{
    protected $fillable = ['parent_id', 'role'];

    protected function casts(): array
    {
        return [
            'role' => TickRole::class,
        ];
    }

    /**
     * De rol van een nieuwe tick: de volgende in de cyclus na die van de parent, ook als de parent is
     * mislukt (Lucas krijgt dan een mail). Zonder parent, of een parent zonder rol: Abstraction.
     */
    public static function nextRole(?self $parent): TickRole
    {
        return $parent?->role?->next() ?? TickRole::Abstraction;
    }

    public function events(): HasMany
    {
        return $this->hasMany(TickEvent::class);
    }

    public function latestEvent(): HasOne
    {
        return $this->hasOne(TickEvent::class)->ofMany(['happened_at' => 'max', 'id' => 'max']);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * De tick zelf, dan zijn parent, diens parent, enzovoort tot de eerste tick.
     *
     * @return Collection<int, self>
     */
    public function getAncestors(): Collection
    {
        $ancestors = new Collection([$this]);

        for ($tick = $this->getParent(); $tick; $tick = $tick->getParent()) {
            $ancestors->push($tick);
        }

        return $ancestors;
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
    public function setStatus(array $event): TickEvent
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
     * De tick die nu loopt (Claimed), of null. Schrijfacties van de agent hangen hieraan; zo hoeft de
     * agent zelf geen tick-id mee te geven.
     */
    public static function current(): ?self
    {
        return static::whereStatus(TickEventLabel::Claimed)->latest('id')->first();
    }

    /**
     * De tick waarop de volgende tick voortbouwt: de laatste tick.
     */
    public static function head(): ?self
    {
        return static::latest('id')->first();
    }

    /**
     * Zet een nieuwe tick klaar met head() als parent, maar alleen als Juno niet gepauzeerd is
     * en er geen tick open staat. Zonder $role: de volgende rol in de cyclus (nextRole).
     */
    public static function schedule(?TickRole $role = null): ?self
    {
        if (Juno::isPaused()) {
            return null;
        }

        return DB::transaction(function () use ($role) {
            if (static::whereStatus(...TickEventLabel::open())->lockForUpdate()->exists()) {
                return null;
            }

            $head = static::head();

            return static::createScheduled($head, $role ?? static::nextRole($head), 'Klaargezet door de scheduler.');
        });
    }

    /**
     * Geeft de oudste klaargezette tick aan de aanroeper, of null als er niets klaarstaat
     * of Juno gepauzeerd is.
     */
    public static function claimNext(): ?self
    {
        if (Juno::isPaused()) {
            return null;
        }

        return DB::transaction(function () {
            $tick = static::whereStatus(TickEventLabel::Scheduled)->oldest('id')->lockForUpdate()->first();

            if (! $tick) {
                return null;
            }

            try {
                $tick->setStatus([
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
     * Draait terug naar $target: zet direct een tick klaar met $target als parent. Die tick
     * wacht tot Juno wordt hervat.
     *
     * @throws InvalidTickTransition
     */
    public static function rollbackTo(self $target, string $description): self
    {
        if (! Juno::isPaused()) {
            throw new InvalidTickTransition('Rollback kan alleen als Juno gepauzeerd is.');
        }

        return DB::transaction(function () use ($target, $description) {
            if (static::whereStatus(...TickEventLabel::open())->lockForUpdate()->exists()) {
                throw new InvalidTickTransition('Rollback kan alleen als er geen tick open staat.');
            }

            if ($target->getStatus()?->label !== TickEventLabel::Finished) {
                throw new InvalidTickTransition("Rollback kan alleen naar een tick die Finished is; tick {$target->id} is dat niet.");
            }

            return static::createScheduled($target, static::nextRole($target), "Rollback naar tick {$target->id}: {$description}");
        });
    }

    /**
     * Geeft geclaimde ticks zonder afsluiting na tick_abandon_after minuten op, en mailt Lucas: de kernel
     * heeft dan niet afgemeld (gecrasht of vastgelopen) en kan zelf geen mail meer sturen.
     */
    public static function abandonStale(): int
    {
        $minutes = (int) config('juno.tick_abandon_after');
        $abandoned = 0;

        foreach (static::whereStatus(TickEventLabel::Claimed)->get() as $tick) {
            if ($tick->getStatus()->happened_at->gt(now()->subMinutes($minutes))) {
                continue;
            }

            $tick->setStatus([
                'label' => TickEventLabel::Abandoned,
                'description' => "Geen Finished of Failed binnen {$minutes} minuten na Claimed.",
            ]);
            $abandoned++;

            $role = $tick->role?->value ?? 'zonder rol';
            MailLucas::trySend(
                "Juno: tick {$tick->id} ({$role}) opgegeven",
                "<p>Tick <strong>{$tick->id}</strong> ({$role}) is opgegeven: de kernel meldde hem niet af binnen {$minutes} minuten na Claimed.</p>"
                .'<p>Waarschijnlijk is de kernel gecrasht of vastgelopen; kijk in <code>/home/juno/kernel/tick.log</code>.</p>'
                ."<p><a href=\"https://juno.lucasjohnston.nl/ticks/{$tick->id}\">Bekijk de tick</a></p>",
            );
        }

        return $abandoned;
    }

    private static function createScheduled(?self $parent, TickRole $role, string $description): self
    {
        $tick = static::create(['parent_id' => $parent?->id, 'role' => $role]);
        $tick->setStatus([
            'label' => TickEventLabel::Scheduled,
            'description' => $description,
        ]);

        return $tick;
    }
}
