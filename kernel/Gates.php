<?php
declare(strict_types=1);

/**
 * Harde gates: deterministisch, gratis, niet om te praten.
 *
 * Elke gate geeft een leesbare foutmelding terug, want die gaat bij een retry
 * rechtstreeks naar de agent. "Afgekeurd" zonder reden leert hem niets.
 *
 * De zachte gate (een judge die een cijfer geeft) komt in fase 3. Vuistregel:
 * zoveel mogelijk naar links duwen, naar het harde.
 */
final class Gates
{
    public function __construct(private array $cfg) {}

    /**
     * Draait vóór alles. Als hier iets misgaat is er geen tick, geen commit,
     * geen kosten — alleen een duidelijke melding.
     *
     * @return string[] fouten; leeg betekent: ga door
     */
    public function preflight(): array
    {
        $e = [];

        // Kan juno alles lezen wat hij nodig heeft? Een editor die een bestand
        // herschrijft laat het eigendom springen, en dan valt de tick halverwege om.
        foreach (['config.php','State.php','Gates.php','Agent.php','CONSTITUTION.md','MISSION.sha256'] as $f) {
            $p = $this->cfg['kernel'] . '/' . $f;
            if (!is_readable($p)) {
                $e[] = "kernel/$f is niet leesbaar (eigendom gesprongen? gebruik setfacl, geen chmod)";
            }
        }

        foreach (['MISSION.md','STATE.json','BACKLOG.md','JOURNAL.md','PLAYBOOK.md','metrics.csv'] as $f) {
            $p = $this->cfg['mission'] . '/' . $f;
            if (!is_readable($p)) {
                $e[] = "mission/$f is niet leesbaar";
            }
        }

        // Het anker mag niet bewogen zijn.
        $missionPath = $this->cfg['mission'] . '/MISSION.md';
        $expected = trim((string)@file_get_contents($this->cfg['kernel'] . '/MISSION.sha256'));
        $actual   = is_readable($missionPath) ? hash_file('sha256', $missionPath) : '';
        if ($expected === '' || $actual === '') {
            $e[] = 'missie-hash kon niet worden vastgesteld';
        } elseif (!hash_equals($expected, $actual)) {
            $e[] = 'MISSION.md wijkt af van kernel/MISSION.sha256 — het anker is verplaatst. '
                 . 'Loop gestopt. Was dit bewust, werk dan de hash bij.';
        }

        // De agent mag niet in de kernel kunnen schrijven.
        if (is_writable($this->cfg['kernel'])) {
            $e[] = 'kernel/ is schrijfbaar voor de draaiende gebruiker — dat hoort niet';
        }

        return $e;
    }

    /**
     * Toetst wat de agent deze tick heeft opgeleverd.
     *
     * @return string[] fouten; leeg betekent: accepteren
     */
    public function check(array $p, array $state): array
    {
        $e = [];

        // 1. Vorm van het antwoord
        foreach (['stanza_index','moment','archetype','stanza','journal'] as $key) {
            if (!isset($p[$key]) || (is_string($p[$key]) && trim($p[$key]) === '')) {
                $e[] = "veld '$key' ontbreekt of is leeg";
            }
        }
        if ($e !== []) {
            return $e; // zonder deze velden heeft de rest geen zin
        }

        // 2. Eén stap per tick: precies de volgende strofe
        $expected = $state['stanzas'] + 1;
        if ((int)$p['stanza_index'] !== $expected) {
            $e[] = "stanza_index is {$p['stanza_index']}, verwacht $expected — één strofe per tick";
        }
        if ($expected > $state['target_stanzas']) {
            $e[] = "de bundel is al compleet ({$state['target_stanzas']} strofen)";
        }

        // 3. Missie-eis 2: vier verschillende archetypen
        $archetype = mb_strtolower(trim((string)$p['archetype']));
        $used = array_map('mb_strtolower', $state['used_archetypes'] ?? []);
        if (in_array($archetype, $used, true)) {
            $e[] = "archetype '{$p['archetype']}' is al gebruikt; elke strofe vraagt een ander archetype";
        }

        // 4. Missie-eis 3: geordend van ochtend naar nacht
        $moments = $this->cfg['moments'];
        $idx = array_search(mb_strtolower(trim((string)$p['moment'])), $moments, true);
        if ($idx === false) {
            $e[] = "moment '{$p['moment']}' is onbekend; kies uit: " . implode(', ', $moments);
        } elseif ($idx < ($state['last_moment_index'] ?? -1)) {
            $e[] = "moment '{$p['moment']}' ligt vóór het vorige moment; de bundel loopt van ochtend naar nacht";
        }

        // 5. Vorm van de strofe
        $lines = array_values(array_filter(
            array_map('rtrim', explode("\n", (string)$p['stanza'])),
            fn($l) => trim($l) !== ''
        ));
        $n = count($lines);
        if ($n < $this->cfg['stanza_min_lines'] || $n > $this->cfg['stanza_max_lines']) {
            $e[] = "strofe heeft $n regels; toegestaan is {$this->cfg['stanza_min_lines']}–{$this->cfg['stanza_max_lines']}";
        }

        // 6. Geen dubbele regels binnen de strofe
        $normalized = array_map(fn($l) => mb_strtolower(trim($l)), $lines);
        if (count($normalized) !== count(array_unique($normalized))) {
            $e[] = 'de strofe bevat een herhaalde regel';
        }

        // 7. Geen regel die al ergens in het gedicht staat
        $existing = mb_strtolower((string)@file_get_contents($this->cfg['artifact']));
        foreach ($lines as $line) {
            $t = mb_strtolower(trim($line));
            if (mb_strlen($t) > 15 && str_contains($existing, $t)) {
                $e[] = "regel komt al elders in het gedicht voor: \"" . trim($line) . '"';
                break;
            }
        }

        return $e;
    }
}
