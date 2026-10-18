<?php
declare(strict_types=1);

/**
 * WEGWERPBAAR — dit is geen echte missie, alleen het kleinste voorbeeld dat de
 * lus laat draaien zodat de testsuite iets heeft om te toetsen.
 *
 * Vervang hem door je eigen klasse (en zet JUNO_MISSIE in .env), of gooi hem
 * weg zodra je eigen missie er staat.
 *
 * Wat hij doet: de agent levert een regel tekst, die wordt toegevoegd aan
 * work/uitvoer.txt. De stand is het aantal regels; klaar bij vijf. Volstrekt
 * inhoudsloos, en dat is het punt — er zit geen domein in dit framework.
 */
final class VoorbeeldMissie implements Missie, MetStub
{
    private const DOEL = 5;

    public function __construct(private Config $config) {}

    public function naam(): string { return 'voorbeeld'; }

    public function verwachteVelden(): array { return ['regel']; }

    public function meet(): Meting
    {
        $pad = $this->config->work . '/uitvoer.txt';
        $regels = is_readable($pad)
            ? count(array_filter(explode("\n", (string) file_get_contents($pad)), fn($r) => trim($r) !== ''))
            : 0;
        return new Meting(waarde: (float) $regels, maximum: (float) self::DOEL, eenheid: 'regels');
    }

    public function gates(): array
    {
        return [
            new VoortgangGate(),
            // Een eigen gate is niets meer dan dit: een klasse met keur() en naam().
            new class implements Gate {
                public function naam(): string { return 'lengte'; }
                public function naMeting(): bool { return false; }
                public function keur(Beurt $beurt): array
                {
                    $regel = trim($beurt->antwoord?->tekst('regel') ?? '');
                    if ($regel === '' || mb_strlen($regel) > 120) {
                        return [new Bevinding($this->naam(),
                            'de regel moet tussen 1 en 120 tekens zijn, '
                            . 'nu ' . mb_strlen($regel))];
                    }
                    return [];
                }
            },
        ];
    }

    public function verwerk(AgentAntwoord $antwoord): void
    {
        file_put_contents(
            $this->config->work . '/uitvoer.txt',
            trim($antwoord->tekst('regel')) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public function context(): array
    {
        $pad = $this->config->work . '/uitvoer.txt';
        $inhoud = is_readable($pad) ? trim((string) file_get_contents($pad)) : '';
        return [
            '# Wat er al staat' => $inhoud === '' ? '(nog niets)' : $inhoud,
        ];
    }

    public function opdracht(): string
    {
        return <<<TXT
        Lever één regel tekst aan, hoogstens 120 tekens, die nog niet in de lijst staat.

        Antwoord met uitsluitend geldige JSON:

        {
          "regel": "<de regel>",
          "journal": "<wat je deed en waarom, één of twee zinnen>",
          "backlog": ["<eerstvolgende stap>"]
        }
        TXT;
    }

    /**
     * Antwoorden voor de stub-agent, per modus. Alleen nodig als je met --stub
     * wilt testen; een missie zonder stub-antwoorden kan niet getest worden
     * zonder tokens uit te geven.
     *
     * @return array<string,array<string,mixed>|null>
     */
    public function stubAntwoorden(): array
    {
        return [
            'ok'        => ['regel' => 'regel ' . bin2hex(random_bytes(3)),
                            'journal' => 'Stub: een regel toegevoegd.',
                            'backlog' => ['Nog een regel.']],
            'stuk'      => null,                       // onleesbare JSON
            'leeg'      => ['regel' => '', 'journal' => 'Stub: lege regel.', 'backlog' => []],
            'te-lang'   => ['regel' => str_repeat('x', 200),
                            'journal' => 'Stub: te lange regel.', 'backlog' => []],
            'geen-veld' => ['journal' => 'Stub: veld ontbreekt.', 'backlog' => []],
        ];
    }
}
