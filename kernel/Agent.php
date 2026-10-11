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
 * Bestaat om de lus te debuggen: lock, vormcontrole, testsuite, voortgangsgate,
 * rollback, journal, commit. De 'ok'-modus levert een échte werkende parser op,
 * want anders test je de voortgangsgate niet — die kijkt naar de testuitslag en
 * laat zich niet voor de gek houden met een placeholder.
 */
final class DryRunAgent implements Agent
{
    /** @param 'ok'|'invalid-json'|'vorm-fout'|'geen-voortgang' $agent_mode */
    public function __construct(
        private array $config,
        private array $state,
        private string $agent_mode = 'ok',
    ) {}

    public function run(string $prompt): array
    {
        $raw_answer = match ($this->agent_mode) {
            'invalid-json'    => 'Hier is je bestand! ```niet eens json```',
            'vorm-fout'       => $this->antwoord('../kernel/tick.php', '<?php // buiten work/'),
            'geen-voortgang'  => $this->antwoord('leeg.php', "<?php\n// doet niets\n"),
            default           => $this->antwoord('interpreter.php', $this->parser()),
        };

        return ['raw' => $raw_answer, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0]];
    }

    private function antwoord(string $bestand, string $inhoud): string
    {
        return json_encode([
            'bestand' => $bestand,
            'inhoud'  => $inhoud,
            'journal' => "Dry-run ({$this->agent_mode}): $bestand ingeleverd. Geen API-aanroep gedaan.",
            'backlog' => ['Volgende groep tests oppakken.'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Een compacte recursive-descent parser die de eerste drie testgroepen haalt:
     * getallen, de vier operatoren, en precedentie met haakjes. Geen variabelen,
     * geen functies — dat is juist de bedoeling: de stub-tick laat de testsuite
     * van 0 naar 19 gaan en stopt daar, zodat je ook een vervolgtick kunt zien
     * afketsen op "geen voortgang".
     */
    private function parser(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

/**
 * Door de dry-run-stub ingeleverd. Haalt groep 1 tot en met 3.
 */
function juno_eval(string $broncode): string
{
    $parser = new class(trim($broncode)) {
        private int $pos = 0;
        public function __construct(private string $t) {}

        public function evalueer(): float
        {
            return $this->som();
        }

        private function spring(): void
        {
            while ($this->pos < strlen($this->t) && ctype_space($this->t[$this->pos])) { $this->pos++; }
        }

        private function kijk(): string
        {
            $this->spring();
            return $this->pos < strlen($this->t) ? $this->t[$this->pos] : '';
        }

        private function som(): float
        {
            $links = $this->product();
            while (true) {
                $teken = $this->kijk();
                if ($teken === '+') { $this->pos++; $links += $this->product(); }
                elseif ($teken === '-') { $this->pos++; $links -= $this->product(); }
                else { return $links; }
            }
        }

        private function product(): float
        {
            $links = $this->unair();
            while (true) {
                $teken = $this->kijk();
                if ($teken === '*') { $this->pos++; $links *= $this->unair(); }
                elseif ($teken === '/') { $this->pos++; $links /= $this->unair(); }
                else { return $links; }
            }
        }

        private function unair(): float
        {
            if ($this->kijk() === '-') { $this->pos++; return -$this->unair(); }
            return $this->atoom();
        }

        private function atoom(): float
        {
            if ($this->kijk() === '(') {
                $this->pos++;
                $waarde = $this->som();
                if ($this->kijk() === ')') { $this->pos++; }
                return $waarde;
            }
            $start = $this->pos;
            while ($this->pos < strlen($this->t)
                   && (ctype_digit($this->t[$this->pos]) || $this->t[$this->pos] === '.')) {
                $this->pos++;
            }
            if ($start === $this->pos) {
                throw new RuntimeException('geen getal op positie ' . $this->pos);
            }
            return (float) substr($this->t, $start, $this->pos - $start);
        }
    };

    $uitkomst = $parser->evalueer();

    // Hele getallen zonder komma teruggeven, zoals de tests verwachten.
    return $uitkomst == (int) $uitkomst ? (string) (int) $uitkomst : (string) $uitkomst;
}
PHP;
    }
}
