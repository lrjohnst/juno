<?php

namespace App\Models;

use App\Enums\AgentEventLabel;
use App\Enums\AgentKind;
use App\Enums\DispatchEventLabel;
use App\Enums\TickEventLabel;
use App\Enums\TickRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Een teamlid. Een agent (kind Agent) hoort bij één rol: de ticks met die rol zijn zijn ticks, en zijn
 * dispatches vormen samen één Claude Code-chat. Lucas (kind Human) heeft geen rol en geen ticks, maar
 * kan berichten sturen en ontvangen.
 */
class Agent extends Model
{
    protected $fillable = ['name', 'kind', 'role', 'role_document_id', 'summary'];

    protected function casts(): array
    {
        return [
            'kind' => AgentKind::class,
            'role' => TickRole::class,
        ];
    }

    public function roleDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'role_document_id');
    }

    /**
     * De documenten die de kernel voor deze agent in workspace/context klaarzet.
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'agent_documents')->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(AgentEvent::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function sentEmails(): HasMany
    {
        return $this->hasMany(AgentEmail::class, 'from_agent_id');
    }

    public function receivedEmails(): HasMany
    {
        return $this->hasMany(AgentEmail::class, 'to_agent_id');
    }

    public static function forTick(?Tick $tick): ?self
    {
        return $tick?->role ? static::where('role', $tick->role)->first() : null;
    }

    public static function findByName(string $name): ?self
    {
        return static::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }

    /**
     * De ticks van deze agent die echt gedraaid hebben (geclaimd), over alle branches heen.
     */
    public function ticks(): Builder
    {
        return Tick::where('role', $this->role)
            ->whereHas('events', fn (Builder $event) => $event->where('label', TickEventLabel::Claimed));
    }

    /**
     * De hoeveelste tick van deze agent $tick is (1 = de eerste).
     */
    public function getTickNumber(Tick $tick): int
    {
        return $this->ticks()->where('id', '<=', $tick->id)->count();
    }

    /**
     * De vorige geclaimde tick van deze agent vóór $tick, of null.
     */
    public function getPreviousTick(Tick $tick): ?Tick
    {
        return $this->ticks()->where('id', '<', $tick->id)->latest('id')->first();
    }

    /**
     * Wanneer de eerste tick van deze agent geclaimd werd, of null.
     */
    public function getStartedAt(): ?Carbon
    {
        $first = $this->ticks()->oldest('id')->first();

        return $first ? self::claimedAt($first) : null;
    }

    public function getLastEvent(AgentEventLabel $label): ?AgentEvent
    {
        return $this->events()->where('label', $label)->orderByDesc('happened_at')->orderByDesc('id')->first();
    }

    public function recordEvent(AgentEventLabel $label, ?Tick $tick = null, ?string $description = null): AgentEvent
    {
        return $this->events()->create([
            'tick_id' => $tick?->id,
            'label' => $label,
            'description' => $description,
            'happened_at' => now(),
        ]);
    }

    /**
     * Het aantal ticks van deze agent dat geclaimd werd na het laatste event $label, tot en met $tick.
     * Zonder zo'n event: alle ticks tot en met $tick. Voorbeeld: 4 na Compacted betekent dat dit de vierde
     * tick is sinds de laatste compactie.
     */
    public function getTicksSince(AgentEventLabel $label, Tick $tick): int
    {
        $event = $this->getLastEvent($label);

        return $this->ticks()
            ->where('id', '<=', $tick->id)
            ->when($event, fn (Builder $ticks) => $ticks->whereHas('events', fn (Builder $e) => $e
                ->where('label', TickEventLabel::Claimed)->where('happened_at', '>', $event->happened_at)))
            ->count();
    }

    /**
     * De Claude Code-chat van deze agent: de session_id van zijn laatste geslaagde dispatch na de laatste
     * SessionReset. null = de volgende dispatch begint een nieuwe chat.
     */
    public function getSessionId(): ?string
    {
        $reset = $this->getLastEvent(AgentEventLabel::SessionReset);

        return DispatchResult::query()
            ->whereNotNull('session_id')
            ->whereHas('dispatch', fn (Builder $dispatch) => $dispatch
                ->where('agent_id', $this->id)
                ->when($reset, fn (Builder $d) => $d->where('created_at', '>', $reset->happened_at))
                ->whereStatus(DispatchEventLabel::Finished))
            ->latest('id')
            ->value('session_id');
    }

    /**
     * Nieuwe berichten bij $tick: alles wat binnenkwam na de Claimed van zijn vorige tick, tot en met de
     * Claimed van $tick. Een bericht dat tijdens $tick binnenkomt, is dus nieuw bij de volgende tick.
     */
    public function getNewEmails(Tick $tick): Collection
    {
        $previous = $this->getPreviousTick($tick);

        return $this->receivedEmails()->with(['from', 'to'])
            ->when($previous, fn ($emails) => $emails->where('created_at', '>', self::claimedAt($previous)))
            ->where('created_at', '<=', self::claimedAt($tick) ?? now())
            ->orderBy('id')
            ->get();
    }

    private static function claimedAt(Tick $tick): ?Carbon
    {
        return $tick->events()->where('label', TickEventLabel::Claimed)->first()?->happened_at;
    }
}
