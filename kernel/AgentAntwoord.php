<?php
declare(strict_types=1);

final class OngeldigAntwoord extends RuntimeException {}

/**
 * Wat de agent inlevert, getypt.
 *
 * Alle validatie van de vorm zit in vanJson(). Daarna is het antwoord
 * typeveilig en hoeft niemand verderop nog te controleren of een veld bestaat.
 *
 * `velden` houdt de missie-specifieke inhoud vast: de kernel weet alleen van
 * `journal` en `backlog` en laat de rest met rust. Zo blijft dit bestand gelijk
 * welke missie je ook draait.
 */
final readonly class AgentAntwoord
{
    /** @param array<string,mixed> $velden */
    public function __construct(
        public array $velden,
        public string $journal,
        public array $backlog,
        public string $rauw,
    ) {}

    /**
     * @param string[] $verwachteVelden missie-specifieke velden die aanwezig en niet leeg moeten zijn
     * @throws OngeldigAntwoord
     */
    public static function vanJson(string $rauw, array $verwachteVelden): self
    {
        // Markdown-fences eromheen zijn een klassieker, ook met een schema.
        $schoon = trim($rauw);
        $schoon = (string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', $schoon);

        $data = json_decode($schoon, true);
        if (! is_array($data)) {
            throw new OngeldigAntwoord('het antwoord was geen geldige JSON');
        }

        $ontbreekt = [];
        foreach ([...$verwachteVelden, 'journal'] as $veld) {
            $leeg = ! array_key_exists($veld, $data)
                 || (is_string($data[$veld]) && trim($data[$veld]) === '');
            if ($leeg) {
                $ontbreekt[] = $veld;
            }
        }
        if ($ontbreekt !== []) {
            throw new OngeldigAntwoord(
                'deze velden ontbreken of zijn leeg: ' . implode(', ', $ontbreekt)
            );
        }

        $velden = [];
        foreach ($verwachteVelden as $veld) {
            $velden[$veld] = $data[$veld];
        }

        return new self(
            velden:  $velden,
            journal: (string) $data['journal'],
            backlog: is_array($data['backlog'] ?? null) ? array_values($data['backlog']) : [],
            rauw:    $rauw,
        );
    }

    public function veld(string $naam): mixed
    {
        return $this->velden[$naam] ?? null;
    }

    public function tekst(string $naam): string
    {
        return (string) ($this->velden[$naam] ?? '');
    }
}
