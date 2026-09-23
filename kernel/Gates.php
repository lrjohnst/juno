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
    public function __construct(private array $config) {}

    /**
     * Draait vóór alles. Als hier iets misgaat is er geen tick, geen commit,
     * geen kosten — alleen een duidelijke melding.
     *
     * @return string[] fouten; leeg betekent: ga door
     */
    public function preflight(): array
    {
        $problems = [];

        // Kan juno alles lezen wat hij nodig heeft? Een editor die een bestand
        // herschrijft laat het eigendom springen, en dan valt de tick halverwege om.
        $required_kernel_files = ['config.php','State.php','Gates.php','Agent.php','AiStudioAgent.php','CONSTITUTION.md','MISSION.sha256'];
        foreach ($required_kernel_files as $kernel_file) {
            if (!is_readable($this->config['kernel'] . '/' . $kernel_file)) {
                $problems[] = "kernel/$kernel_file is niet leesbaar "
                            . '(eigendom gesprongen? gebruik setfacl, geen chmod)';
            }
        }

        $required_mission_files = ['MISSION.md','STATE.json','BACKLOG.md','JOURNAL.md','PLAYBOOK.md','metrics.csv'];
        foreach ($required_mission_files as $mission_file) {
            if (!is_readable($this->config['mission'] . '/' . $mission_file)) {
                $problems[] = "mission/$mission_file is niet leesbaar";
            }
        }

        // Het anker mag niet bewogen zijn.
        $mission_path  = $this->config['mission'] . '/MISSION.md';
        $expected_hash = trim((string)@file_get_contents($this->config['kernel'] . '/MISSION.sha256'));
        $actual_hash   = is_readable($mission_path) ? hash_file('sha256', $mission_path) : '';
        if ($expected_hash === '' || $actual_hash === '') {
            $problems[] = 'missie-hash kon niet worden vastgesteld';
        } elseif (!hash_equals($expected_hash, $actual_hash)) {
            $problems[] = 'MISSION.md wijkt af van kernel/MISSION.sha256 — het anker is verplaatst. '
                        . 'Loop gestopt. Was dit bewust, werk dan de hash bij.';
        }

        // De agent mag niet in de kernel kunnen schrijven.
        if (is_writable($this->config['kernel'])) {
            $problems[] = 'kernel/ is schrijfbaar voor de draaiende gebruiker — dat hoort niet';
        }

        return $problems;
    }

    /**
     * Toetst wat de agent deze tick heeft opgeleverd.
     *
     * @return string[] fouten; leeg betekent: accepteren
     */
    public function check(array $answer, array $state): array
    {
        $errors = [];

        // 1. Vorm van het antwoord
        $required_fields = ['stanza_index','moment','archetype','stanza','journal'];
        foreach ($required_fields as $field_name) {
            $is_missing = !isset($answer[$field_name])
                       || (is_string($answer[$field_name]) && trim($answer[$field_name]) === '');
            if ($is_missing) {
                $errors[] = "veld '$field_name' ontbreekt of is leeg";
            }
        }
        if ($errors !== []) {
            return $errors; // zonder deze velden heeft de rest geen zin
        }

        // 2. Eén stap per tick: precies de volgende strofe
        $expected_index = $state['stanzas'] + 1;
        if ((int)$answer['stanza_index'] !== $expected_index) {
            $errors[] = "stanza_index is {$answer['stanza_index']}, verwacht $expected_index — één strofe per tick";
        }
        if ($expected_index > $state['target_stanzas']) {
            $errors[] = "de bundel is al compleet ({$state['target_stanzas']} strofen)";
        }

        // 3. Missie-eis 2: vier verschillende archetypen
        $declared_archetype     = mb_strtolower(trim((string)$answer['archetype']));
        $used_archetypes_lower  = array_map('mb_strtolower', $state['used_archetypes'] ?? []);
        if (in_array($declared_archetype, $used_archetypes_lower, true)) {
            $errors[] = "archetype '{$answer['archetype']}' is al gebruikt; elke strofe vraagt een ander archetype";
        }

        // 4. Missie-eis 3: geordend van ochtend naar nacht
        $known_moments          = $this->config['moments'];
        $declared_moment_index  = array_search(mb_strtolower(trim((string)$answer['moment'])), $known_moments, true);
        $previous_moment_index  = $state['last_moment_index'] ?? -1;
        if ($declared_moment_index === false) {
            $errors[] = "moment '{$answer['moment']}' is onbekend; kies uit: " . implode(', ', $known_moments);
        } elseif ($declared_moment_index < $previous_moment_index) {
            $errors[] = "moment '{$answer['moment']}' ligt vóór het vorige moment; "
                      . 'de bundel loopt van ochtend naar nacht';
        }

        // 5. Vorm van de strofe
        $stanza_lines = array_values(array_filter(
            array_map('rtrim', explode("\n", (string)$answer['stanza'])),
            fn(string $line): bool => trim($line) !== ''
        ));
        $line_count = count($stanza_lines);
        if ($line_count < $this->config['stanza_min_lines'] || $line_count > $this->config['stanza_max_lines']) {
            $errors[] = "strofe heeft $line_count regels; toegestaan is "
                      . "{$this->config['stanza_min_lines']}–{$this->config['stanza_max_lines']}";
        }

        // 6. Geen dubbele regels binnen de strofe
        $normalized_lines = array_map(fn(string $line): string => mb_strtolower(trim($line)), $stanza_lines);
        if (count($normalized_lines) !== count(array_unique($normalized_lines))) {
            $errors[] = 'de strofe bevat een herhaalde regel';
        }

        // 7. Geen regel die al ergens in het gedicht staat
        $poem_so_far = mb_strtolower((string)@file_get_contents($this->config['artifact']));
        foreach ($stanza_lines as $stanza_line) {
            $normalized_line = mb_strtolower(trim($stanza_line));
            if (mb_strlen($normalized_line) > 15 && str_contains($poem_so_far, $normalized_line)) {
                $errors[] = 'regel komt al elders in het gedicht voor: "' . trim($stanza_line) . '"';
                break;
            }
        }

        return $errors;
    }
}
