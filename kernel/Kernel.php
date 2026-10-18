<?php
declare(strict_types=1);

/**
 * De lus. Dom met opzet: stelt een opdracht samen, laat de agent werken, toetst
 * het resultaat en legt vast wat er gebeurde. Geen enkel oordeel over de inhoud —
 * dat is wat de missie en de gates doen.
 *
 * Geeft een Uitkomst terug in plaats van te exit()'en, zodat de lus testbaar is.
 * tick.php zet die uitkomst om in een exitcode.
 */
final class Kernel
{
    /** @var callable(string):void */
    private $log;
    private $lockHandle = null;

    public function __construct(
        private Config $config,
        private Missie $missie,
        private Agent $agent,
        private State $state,
        ?callable $log = null,
    ) {
        $this->log = $log ?? static function (string $regel): void { echo $regel . "\n"; };
    }

    public function draai(): Uitkomst
    {
        // ── slot ─────────────────────────────────────────────────────────────
        // Een tick kan langer duren dan het ritme waarop cron vuurt. Zonder slot
        // schrijven er twee tegelijk in dezelfde map.
        @mkdir($this->config->var, 0700, true);
        $this->lockHandle = fopen($this->config->var . '/tick.lock', 'c');
        if ($this->lockHandle === false || ! flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            $this->zeg('Er draait al een tick. Niets gedaan.');
            return Uitkomst::Overgeslagen;
        }

        try {
            return $this->beurt();
        } finally {
            if ($this->lockHandle !== false && $this->lockHandle !== null) {
                flock($this->lockHandle, LOCK_UN);
                fclose($this->lockHandle);
            }
        }
    }

    private function beurt(): Uitkomst
    {
        // ── noodstop ─────────────────────────────────────────────────────────
        if (file_exists($this->config->var . '/STOP')) {
            $this->zeg('STOP-bestand aanwezig. Niets gedaan.');
            return Uitkomst::Overgeslagen;
        }

        $staat = $this->state->lees();
        $tick  = (int) $staat['tick'] + 1;
        $voor  = $this->missie->meet();
        $beurt = new Beurt($this->config, $tick, $voor);

        // ── vooraf: anker en rechten ─────────────────────────────────────────
        // Liever hier stoppen met een leesbare melding dan halverwege omvallen.
        $vooraf = $this->keur($this->vooraffGates(), $beurt);
        if ($vooraf !== []) {
            foreach ($vooraf as $bevinding) {
                $this->zeg("  preflight [{$bevinding->gate}] {$bevinding->melding}");
            }
            return Uitkomst::Gestopt;
        }

        $this->zeg(sprintf('── tick %d · %s · stand %s %s',
            $tick, $this->missie->naam(), $voor, str_repeat('─', 20)));

        // ── dagcap ───────────────────────────────────────────────────────────
        $vandaag = $this->state->ticksVandaag();
        if ($vandaag >= $this->config->maxTicksPerDag) {
            $this->zeg("Dagcap bereikt ($vandaag ticks vandaag). Niets gedaan.");
            return Uitkomst::Overgeslagen;
        }

        // ── klaar? ───────────────────────────────────────────────────────────
        if ($voor->isKlaar()) {
            $this->zeg("De missie is behaald ($voor).");
            return Uitkomst::Overgeslagen;
        }

        // ── escalatie ────────────────────────────────────────────────────────
        // Een autonoom systeem dat niet weet wanneer het moet ophouden, is een lek.
        if ((int) $staat['mislukkingen_op_rij'] >= $this->config->maxMislukkingen) {
            $this->zeg("{$staat['mislukkingen_op_rij']} mislukkingen op rij. Gestopt — dit vraagt een mens.");
            $this->state->journaal($tick, Uitkomst::Gestopt,
                'Loop gepauzeerd na herhaald falen. Zet mislukkingen_op_rij in STATE.json op 0 om door te gaan.');
            return Uitkomst::Gestopt;
        }

        // ── opdracht en retry-lus ────────────────────────────────────────────
        $opdracht = $this->opdracht($voor);
        [$uitkomst, $antwoord, $na, $bevindingen, $verbruik, $reden] =
            $this->probeer($opdracht, $beurt);

        // ── vastleggen ───────────────────────────────────────────────────────
        $this->boek($uitkomst, $tick, $staat, $voor, $na, $antwoord, $bevindingen, $verbruik, $reden);
        return $uitkomst;
    }

    /**
     * De retry-lus. Bij een afkeuring krijgt de agent de bevindingen terug — dat
     * is de enige terugkoppeling die hij heeft, dus daar hoort te staan wat er
     * mis was, niet dát er iets mis was.
     *
     * @return array{0:Uitkomst,1:?AgentAntwoord,2:?Meting,3:Bevinding[],4:array,5:?string}
     */
    private function probeer(string $opdracht, Beurt $beurt): array
    {
        $pogingen    = $this->config->maxRetries + 1;
        $bevindingen = [];
        $verbruik    = ['input_tokens' => 0, 'output_tokens' => 0];
        $na          = null;

        for ($poging = 1; $poging <= $pogingen; $poging++) {
            $beurt->poging = $poging;
            $volledig = $bevindingen === []
                ? $opdracht
                : $opdracht . "\n\n# Je vorige poging is afgekeurd\n\n"
                            . Bevinding::alsLijst($bevindingen)
                            . "\n\nLos dit op en lever opnieuw.";

            try {
                $resultaat = $this->agent->vraag($volledig);
            } catch (AgentOnbereikbaar $e) {
                // Niet nogmaals proberen: bij een leeg tegoed of een storing levert
                // een tweede poging alleen een tweede timeout op.
                $this->zeg('  agent onbereikbaar: ' . $e->getMessage());
                return [Uitkomst::Onbereikbaar, null, null, [], $verbruik, $e->getMessage()];
            }
            $verbruik = $resultaat['verbruik'];

            try {
                $antwoord = AgentAntwoord::vanJson($resultaat['rauw'], $this->missie->verwachteVelden());
            } catch (OngeldigAntwoord $e) {
                $bevindingen = [new Bevinding('vorm', $e->getMessage())];
                $this->zeg("  poging $poging: " . $e->getMessage());
                continue;
            }

            $beurt->antwoord = $antwoord;
            $beurt->na = null;

            // Eerst de controles die geen bijwerking nodig hebben.
            $bevindingen = $this->keur($this->missie->gates(), $beurt, naMeting: false);
            if ($bevindingen !== []) {
                $this->zeg("  poging $poging: afgekeurd — " . Bevinding::alsRegel($bevindingen));
                if (Bevinding::bevatFatale($bevindingen)) {
                    break;
                }
                continue;
            }

            // Wegschrijven, dan opnieuw meten en de rest van de gates laten kijken.
            $this->missie->verwerk($antwoord);
            $na = $this->missie->meet();
            $beurt->na = $na;

            $bevindingen = $this->keur($this->missie->gates(), $beurt, naMeting: true);
            if ($bevindingen === []) {
                $this->zeg(sprintf('  poging %d: %s → %s, geaccepteerd', $poging, $beurt->voor, $na));
                return [Uitkomst::Geaccepteerd, $antwoord, $na, [], $verbruik, null];
            }

            // Afgekeurd: terugdraaien zodat de volgende poging van de laatste
            // goede staat uitgaat, niet van een halve.
            $this->rolTerug();
            $na = null;
            $this->zeg("  poging $poging: afgekeurd — " . Bevinding::alsRegel($bevindingen));
            if (Bevinding::bevatFatale($bevindingen)) {
                break;
            }
        }

        return [Uitkomst::Afgekeurd, null, null, $bevindingen, $verbruik, null];
    }

    /**
     * @param Gate[] $gates
     * @return Bevinding[]
     */
    private function keur(array $gates, Beurt $beurt, ?bool $naMeting = null): array
    {
        $alles = [];
        foreach ($gates as $gate) {
            // Een gate die de hermeting nodig heeft, draait alleen in die ronde.
            if ($naMeting !== null && $gate->naMeting() !== $naMeting) {
                continue;
            }
            foreach ($gate->keur($beurt) as $bevinding) {
                $alles[] = $bevinding;
            }
        }
        return $alles;
    }

    /** @return Gate[] */
    private function vooraffGates(): array
    {
        return [
            new AnkerGate($this->config),
            new RechtenGate(
                leesbaar: [
                    $this->config->mission . '/MISSION.md',
                    $this->config->mission . '/STATE.json',
                    $this->config->kernel . '/CONSTITUTION.md',
                ],
                nietSchrijfbaar: [$this->config->kernel],
            ),
        ];
    }

    /** De volledige opdracht, zonder iets aan te roepen. Voor --toon-opdracht. */
    public function toonOpdracht(): string
    {
        return $this->opdracht($this->missie->meet());
    }

    /**
     * De opdracht. Dit is het enige werkelijk interessante wat de kernel doet:
     * bepalen wat de agent te zien krijgt. Niet alles kan mee — het geheugen zit
     * in bestanden, niet in een gespreksgeschiedenis.
     */
    private function opdracht(Meting $voor): string
    {
        $blokken = [
            $this->state->get('MISSION.md'),
            '# Werkregels' => (string) @file_get_contents($this->config->kernel . '/CONSTITUTION.md'),
            '# Waar we staan' => "Stand: $voor\n\n```json\n" . $this->state->get('STATE.json') . "\n```",
        ];

        foreach ($this->missie->context() as $kop => $inhoud) {
            $blokken[$kop] = $inhoud;
        }

        $blokken['# Backlog']      = $this->state->get('BACKLOG.md');
        $blokken['# Playbook']     = $this->state->get('PLAYBOOK.md');
        $blokken['# Laatste ticks'] = $this->state->recenteJournaal($this->config->journalVenster);

        $inbox = $this->state->inbox();
        if ($inbox !== []) {
            $blokken['# Berichten van buiten'] = implode("\n\n", array_map(
                fn(string $naam, string $tekst): string => "### $naam\n$tekst",
                array_keys($inbox), $inbox
            ));
        }

        $blokken['# Jouw opdracht deze tick'] = $this->missie->opdracht();

        $tekst = [];
        foreach ($blokken as $kop => $inhoud) {
            $tekst[] = is_int($kop) ? $inhoud : "$kop\n\n$inhoud";
        }
        return implode("\n\n---\n\n", $tekst);
    }

    /** Draait het werkstuk terug naar de laatste commit. mission/ blijft staan. */
    private function rolTerug(): void
    {
        exec('git -C ' . escapeshellarg($this->config->root) . ' checkout -- work/ 2>&1');
    }

    private function boek(
        Uitkomst $uitkomst, int $tick, array $staat, Meting $voor, ?Meting $na,
        ?AgentAntwoord $antwoord, array $bevindingen, array $verbruik, ?string $reden,
    ): void {
        if ($uitkomst === Uitkomst::Onbereikbaar) {
            // Geen tick gedaan: tick-nummer en faalteller blijven staan. Wel
            // vastleggen, zodat je in metrics.csv ziet hoe vaak dit gebeurde.
            $this->state->journaal($tick, $uitkomst,
                "Tick niet uitgevoerd: $reden\n\nDe stand is ongewijzigd; "
                . 'de volgende tick probeert dezelfde stap opnieuw.');
            $this->state->metriek($this->metriekRij($tick, $uitkomst, $voor, $verbruik, (string) $reden));
            $this->zeg('  ONBEREIKBAAR — stand ongewijzigd, niets gecommit');
            return;
        }

        $geslaagd = $uitkomst === Uitkomst::Geaccepteerd;

        $staat['tick']                = $tick;
        $staat['laatste_uitkomst']    = $uitkomst->value;
        $staat['stand']               = (string) ($geslaagd ? $na : $voor);
        $staat['status']              = ($na?->isKlaar() ?? false) ? 'compleet' : 'bezig';
        $staat['mislukkingen_op_rij'] = $uitkomst->teltAlsMislukking()
            ? (int) $staat['mislukkingen_op_rij'] + 1
            : 0;

        if ($geslaagd && $antwoord !== null && $antwoord->backlog !== []) {
            $items = implode("\n", array_map(fn(string $r): string => "1. $r", $antwoord->backlog));
            $this->state->put('BACKLOG.md',
                "# Backlog\n\nBovenste item is de eerstvolgende stap. juno onderhoudt deze lijst zelf.\n\n"
                . "## Open\n\n$items\n\n## Afgerond\n\n_(zie JOURNAL.md)_\n");
        }

        $noot = $geslaagd
            ? sprintf('%s → %s', $voor, $na)
            : Bevinding::alsRegel(array_slice($bevindingen, 0, 1));

        $journaal = $geslaagd
            ? ($antwoord?->journal ?? '')
            : 'Afgekeurd. Reden(en): ' . Bevinding::alsRegel($bevindingen)
              . "\n\nHet werkstuk is teruggedraaid. Deze entry blijft staan, zodat de "
              . 'volgende tick niet dezelfde fout maakt.';

        $this->state->schrijf($staat);
        $this->state->journaal($tick, $uitkomst, $journaal);
        $this->state->metriek($this->metriekRij($tick, $uitkomst, $geslaagd ? $na : $voor, $verbruik, $noot));

        exec('git -C ' . escapeshellarg($this->config->root) . ' add mission/ work/ 2>&1');
        $bericht = sprintf('tick %d: %s — %s', $tick, strtolower($uitkomst->value),
            $noot === '' ? 'geen bijzonderheden' : $noot);
        exec('git -C ' . escapeshellarg($this->config->root) . ' commit -q -m '
             . escapeshellarg($bericht) . ' 2>&1', $uit, $code);

        $this->zeg("  {$uitkomst->value} — stand " . ($geslaagd ? $na : $voor));
        $this->zeg('  ' . ($code === 0 ? 'gecommit' : 'niets te committen'));
    }

    private function metriekRij(int $tick, Uitkomst $uitkomst, ?Meting $stand, array $verbruik, string $noot): array
    {
        return [
            'tick'          => $tick,
            'timestamp'     => date('c'),
            'uitkomst'      => $uitkomst->value,
            'stand'         => $stand?->waarde ?? '',
            'maximum'       => $stand?->maximum ?? '',
            'agent'         => $this->agent->naam(),
            'input_tokens'  => $verbruik['input_tokens'] ?? 0,
            'output_tokens' => $verbruik['output_tokens'] ?? 0,
            'noot'          => $noot,
        ];
    }

    private function zeg(string $regel): void
    {
        ($this->log)($regel);
    }
}
