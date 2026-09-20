<?php
declare(strict_types=1);

/**
 * De agent is het enige onvoorspelbare onderdeel van dit systeem.
 * De kernel praat met hem via één methode en vertrouwt niets van wat er terugkomt.
 */
interface Agent
{
    /** @return array{raw:string, usage:array} */
    public function run(string $prompt): array;
}

/**
 * Doet geen enkele aanroep en kost niets.
 *
 * Bestaat om de lus te debuggen: lock, gates, rollback, journal, commit.
 * Pas als honderd stub-ticks foutloos draaien is het zinnig om tokens uit te geven.
 */
final class DryRunAgent implements Agent
{
    /** @param 'ok'|'invalid-json'|'gate-fail' $mode */
    public function __construct(
        private array $cfg,
        private array $state,
        private string $mode = 'ok',
    ) {}

    public function run(string $prompt): array
    {
        $raw = match ($this->mode) {
            'invalid-json' => 'Hier is je strofe! ```niet eens json```',
            'gate-fail'    => $this->payload(brokenMoment: true),
            default        => $this->payload(),
        };

        return ['raw' => $raw, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0]];
    }

    private function payload(bool $brokenMoment = false): string
    {
        $i = $this->state['stanzas'] + 1;
        $moments = $this->cfg['moments'];
        // bij een correcte tick loopt het moment netjes mee met de index
        $moment = $brokenMoment
            ? $moments[0]                                   // terug naar de ochtend: schendt eis 3
            : $moments[min($i - 1, count($moments) - 1)];

        $stanzas = [
            1 => ["De straat ligt nog in laken gehuld,", "een rolluik rammelt zich wakker.", "Niemand kijkt op; de mist", "houdt alles op veilige afstand."],
            2 => ["Ze kijkt hem aan over de tafel heen,", "die blik die alles al weet", "voordat hij zijn mond opendoet.", "Hij eet door en weet het ook."],
            3 => ["De bocht, en dan die ander,", "die zomaar voor je schuift.", "Een hand gaat omhoog, half gebaar,", "half iets wat je thuis zou laten."],
            4 => ["Het huis kraakt zijn dag uit.", "Boven slaapt iemand al,", "beneden blijft één lamp branden", "voor niemand in het bijzonder."],
        ];

        $archetypes = [1 => 'lege ochtendstraat', 2 => 'blik van een moeder', 3 => 'afgesneden in de bocht', 4 => 'het huis dat uitdooft'];

        return json_encode([
            'stanza_index' => $i,
            'moment'       => $moment,
            'archetype'    => $archetypes[$i] ?? "stub-archetype-$i",
            'stanza'       => implode("\n", $stanzas[$i] ?? ["Stub regel een.", "Stub regel twee.", "Stub regel drie."]),
            'journal'      => "Dry-run: strofe $i neergezet ($moment). Geen API-aanroep gedaan.",
            'backlog'      => $i < $this->state['target_stanzas']
                ? ['Schrijf strofe ' . ($i + 1) . ': kies een archetype dat later op de dag speelt.']
                : ['Bundel compleet — laat de critic de samenhang beoordelen.'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
