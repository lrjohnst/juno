<?php

namespace App\Models;

use App\Enums\ApiTokenEventLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Een token voor de API van de handler, met eigen rechten. Alleen de SHA-256 staat hier; het token zelf
 * wordt bij uitgifte één keer getoond. Of het geldig is, staat in api_token_events (Issued / Revoked).
 */
class ApiToken extends Model
{
    public const ABILITIES = [
        'kernel' => 'Kernel: ticks, documenten, journal, dispatches, mail',
        'library.read' => 'Library lezen',
        'library.write' => 'Library schrijven',
        'journal.read' => 'Journal lezen',
        'journal.write' => 'Journal schrijven (bij de tick die loopt)',
        'email.read' => 'Berichten van en aan de agent van de tick die loopt lezen',
        'email.send' => 'Berichten versturen namens de agent van de tick die loopt',
    ];

    protected $fillable = ['name', 'token_hash'];

    protected $hidden = ['token_hash'];

    public function abilities(): HasMany
    {
        return $this->hasMany(ApiTokenAbility::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApiTokenEvent::class);
    }

    /**
     * Zoekt het geldige token bij een platte tekst, of null.
     */
    public static function findActive(?string $plain): ?self
    {
        if (! $plain) {
            return null;
        }

        $token = static::where('token_hash', hash('sha256', $plain))->first();

        return $token?->isActive() ? $token : null;
    }

    /**
     * Geeft een nieuw token uit en geeft het één keer terug.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $name, array $abilities, ?string $description = null): array
    {
        $plain = Str::random(64);

        return [static::register($name, hash('sha256', $plain), $abilities, $description), $plain];
    }

    /**
     * Legt een token vast bij een bestaande hash (bijv. het kernel-token uit .env).
     */
    public static function register(string $name, string $hash, array $abilities, ?string $description = null): self
    {
        return DB::transaction(function () use ($name, $hash, $abilities, $description) {
            $token = static::create(['name' => $name, 'token_hash' => $hash]);
            $token->setAbilities($abilities);
            $token->setStatus(['label' => ApiTokenEventLabel::Issued, 'description' => $description]);

            return $token;
        });
    }

    public function getStatus(): ?ApiTokenEvent
    {
        return $this->events()->orderByDesc('happened_at')->orderByDesc('id')->first();
    }

    public function setStatus(array $event): ApiTokenEvent
    {
        $label = $event['label'] instanceof ApiTokenEventLabel ? $event['label'] : ApiTokenEventLabel::from($event['label']);
        $current = $this->getStatus()?->label;

        if ($label === ApiTokenEventLabel::Issued ? $current !== null : $current !== ApiTokenEventLabel::Issued) {
            throw new LogicException("Token {$this->name}: {$label->value} kan niet volgen op ".($current?->value ?? 'niets').'.');
        }

        return $this->events()->create([
            'label' => $label,
            'description' => $event['description'] ?? null,
            'happened_at' => now(),
        ]);
    }

    public function isActive(): bool
    {
        return $this->getStatus()?->label === ApiTokenEventLabel::Issued;
    }

    public function revoke(string $description): ApiTokenEvent
    {
        return $this->setStatus(['label' => ApiTokenEventLabel::Revoked, 'description' => $description]);
    }

    public function can(string $ability): bool
    {
        return $this->abilities()->where('ability', $ability)->exists();
    }

    public function setAbilities(array $abilities): void
    {
        $abilities = array_values(array_intersect($abilities, array_keys(self::ABILITIES)));

        $this->abilities()->whereNotIn('ability', $abilities)->delete();
        foreach ($abilities as $ability) {
            $this->abilities()->firstOrCreate(['ability' => $ability]);
        }
    }
}
