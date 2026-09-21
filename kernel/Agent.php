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
    /** @param 'ok'|'invalid-json'|'gate-fail' $agent_mode */
    public function __construct(
        private array $config,
        private array $state,
        private string $agent_mode = 'ok',
    ) {}

    public function run(string $prompt): array
    {
        $raw_answer = match ($this->agent_mode) {
            'invalid-json' => 'Hier is je strofe! ```niet eens json```',
            'gate-fail'    => $this->fakeAnswer(break_a_gate: true),
            default        => $this->fakeAnswer(),
        };

        return [
            'raw'   => $raw_answer,
            'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
        ];
    }

    private function fakeAnswer(bool $break_a_gate = false): string
    {
        $stanza_index  = $this->state['stanzas'] + 1;
        $known_moments = $this->config['moments'];
        $moment        = $known_moments[min($stanza_index - 1, count($known_moments) - 1)];

        $stanza_texts = [
            1 => ["De straat ligt nog in laken gehuld,", "een rolluik rammelt zich wakker.", "Niemand kijkt op; de mist", "houdt alles op veilige afstand."],
            2 => ["Ze kijkt hem aan over de tafel heen,", "die blik die alles al weet", "voordat hij zijn mond opendoet.", "Hij eet door en weet het ook."],
            3 => ["De bocht, en dan die ander,", "die zomaar voor je schuift.", "Een hand gaat omhoog, half gebaar,", "half iets wat je thuis zou laten."],
            4 => ["Het huis kraakt zijn dag uit.", "Boven slaapt iemand al,", "beneden blijft één lamp branden", "voor niemand in het bijzonder."],
        ];

        $archetype_names = [
            1 => 'lege ochtendstraat',
            2 => 'blik van een moeder',
            3 => 'afgesneden in de bocht',
            4 => 'het huis dat uitdooft',
        ];

        // gate-fail: hergebruik het archetype van strofe 1 — schendt missie-eis 2
        $declared_archetype = $break_a_gate
            ? 'lege ochtendstraat'
            : ($archetype_names[$stanza_index] ?? "stub-archetype-$stanza_index");

        $fallback_stanza = ["Stub regel een.", "Stub regel twee.", "Stub regel drie."];

        $next_backlog = $stanza_index < $this->state['target_stanzas']
            ? ['Schrijf strofe ' . ($stanza_index + 1) . ': kies een archetype dat later op de dag speelt.']
            : ['Bundel compleet — laat de critic de samenhang beoordelen.'];

        return json_encode([
            'stanza_index' => $stanza_index,
            'moment'       => $moment,
            'archetype'    => $declared_archetype,
            'stanza'       => implode("\n", $stanza_texts[$stanza_index] ?? $fallback_stanza),
            'journal'      => "Dry-run: strofe $stanza_index neergezet ($moment). Geen API-aanroep gedaan.",
            'backlog'      => $next_backlog,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
