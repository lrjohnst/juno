<?php
declare(strict_types=1);

/**
 * Een agent die niets aanroept en niets kost.
 *
 * Bestaat om de lus te debuggen: lock, gates, rollback, journal, commit. Geef
 * hem per modus een antwoord mee; `null` betekent onleesbaar, zodat je ook die
 * tak kunt testen.
 *
 *   new StubAgent(['ok' => ['tekst' => 'iets'], 'stuk' => null], 'ok')
 */
final class StubAgent implements Agent
{
    /** @param array<string,array<string,mixed>|null> $antwoorden */
    public function __construct(
        private array $antwoorden,
        private string $modus = 'ok',
    ) {}

    public function naam(): string { return "stub:{$this->modus}"; }

    public function vraag(string $opdracht): array
    {
        if (! array_key_exists($this->modus, $this->antwoorden)) {
            throw new AgentOnbereikbaar("stub kent de modus '{$this->modus}' niet");
        }

        $antwoord = $this->antwoorden[$this->modus];
        $rauw = $antwoord === null
            ? 'dit is geen JSON'
            : (string) json_encode($antwoord, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['rauw' => $rauw, 'verbruik' => ['input_tokens' => 0, 'output_tokens' => 0]];
    }
}
