<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een bron in de library: één rij per bron. Bewerkbaar (metadata); de aantekeningen hebben versies.
 * DOI, ISBN en URL worden bij het opslaan genormaliseerd, zodat dezelfde bron niet twee keer binnenkomt.
 */
class Source extends Model
{
    public const FIELDS = ['id', 'tick_id', 'authors', 'title', 'year', 'doi', 'isbn', 'url', 'description', 'created_at', 'updated_at'];

    protected $fillable = ['tick_id', 'authors', 'title', 'year', 'doi', 'isbn', 'url', 'description'];

    protected $hidden = ['url_normalized'];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Source $source) {
            $source->doi = self::normalizeDoi($source->doi);
            $source->isbn = self::normalizeIsbn($source->isbn);
            $source->url_normalized = self::normalizeUrl($source->url);
        });
    }

    public function tick(): BelongsTo
    {
        return $this->belongsTo(Tick::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SourceNote::class);
    }

    /**
     * Een bestaande bron met dezelfde DOI, ISBN of URL, of null. $except: de bron zelf, bij bewerken.
     */
    public static function findDuplicate(?string $doi, ?string $isbn, ?string $url, ?int $except = null): ?self
    {
        $keys = array_filter([
            'doi' => self::normalizeDoi($doi),
            'isbn' => self::normalizeIsbn($isbn),
            'url_normalized' => self::normalizeUrl($url),
        ]);

        if ($keys === []) {
            return null;
        }

        return static::where(function ($query) use ($keys) {
            foreach ($keys as $column => $value) {
                $query->orWhere($column, $value);
            }
        })->when($except, fn ($query) => $query->whereKeyNot($except))->first();
    }

    public static function normalizeDoi(?string $doi): ?string
    {
        $doi = strtolower(trim((string) $doi));
        $doi = preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#', '', $doi);

        return $doi !== '' ? $doi : null;
    }

    public static function normalizeIsbn(?string $isbn): ?string
    {
        $isbn = strtoupper(preg_replace('/[^0-9Xx]/', '', (string) $isbn));

        return $isbn !== '' ? $isbn : null;
    }

    /**
     * Zonder schema, www en afsluitende slash, zonder #fragment en zonder utm-parameters; host in kleine letters.
     */
    public static function normalizeUrl(?string $url): ?string
    {
        $parts = parse_url(trim((string) $url));

        if (empty($parts['host'])) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn ($key) => ! str_starts_with((string) $key, 'utm_'), ARRAY_FILTER_USE_KEY);
        ksort($query);

        $normalized = preg_replace('/^www\./', '', strtolower($parts['host']))
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/')
            .($query ? '?'.http_build_query($query) : '');

        return substr($normalized, 0, 191);
    }
}
