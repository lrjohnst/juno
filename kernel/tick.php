#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * tick.php — één heartbeat van juno.
 *
 * De kernel is met opzet dom: hij velt geen oordeel, hij stelt een prompt samen,
 * roept de agent aan, toetst het resultaat tegen harde regels en legt vast wat er
 * gebeurde. Alle intelligentie zit in de agent; alle betrouwbaarheid hier.
 *
 * Gebruik:
 *   php tick.php --dry-run             normale stub-tick, geen kosten
 *   php tick.php --dry-run --mode=gate-fail      forceert een afkeuring
 *   php tick.php --dry-run --mode=invalid-json   forceert onleesbaar antwoord
 *   php tick.php --verbose             toont de samengestelde prompt
 *   php tick.php --model=mistral-small-latest   ander model dan de default
 *
 * Exit codes: 0 = tick gedaan, 1 = fout, 2 = bewust overgeslagen.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/State.php';
require __DIR__ . '/Gates.php';
require __DIR__ . '/Agent.php';
require __DIR__ . '/AiStudioAgent.php';

$command_options = getopt('', ['dry-run', 'mode::', 'verbose', 'model::']);
$is_dry_run      = isset($command_options['dry-run']);
$agent_mode      = (string)($command_options['mode'] ?? 'ok');
$show_prompt     = isset($command_options['verbose']);
$model_override  = isset($command_options['model']) ? (string)$command_options['model'] : null;

function say(string $message): void { echo $message . "\n"; }
function fail(string $message): never { fwrite(STDERR, "FOUT: $message\n"); exit(1); }

// ─── 1. Lock ─────────────────────────────────────────────────────────────────
// Cron vuurt op een vast ritme, maar een tick kan langer duren dan dat ritme.
// Zonder lock schrijven twee agents tegelijk in dezelfde map.
@mkdir($config['var'], 0700, true);
$lock_handle = fopen($config['var'] . '/tick.lock', 'c');
if ($lock_handle === false || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
    say('Er draait al een tick. Niets gedaan.');
    exit(2);
}

// ─── 2. Kill switch ──────────────────────────────────────────────────────────
if (file_exists($config['var'] . '/STOP')) {
    say('STOP-bestand aanwezig. Niets gedaan.');
    exit(2);
}

// ─── 3. Preflight ────────────────────────────────────────────────────────────
// Liever hier stoppen met een leesbare melding dan halverwege omvallen.
$gate_checker = new Gates($config);
$preflight_problems = $gate_checker->preflight();
if ($preflight_problems !== []) {
    foreach ($preflight_problems as $preflight_problem) {
        fwrite(STDERR, "preflight: $preflight_problem\n");
    }
    exit(1);
}

$state_store = new State($config);
$state       = $state_store->read();

// ─── 4. Dagcap ───────────────────────────────────────────────────────────────
$ticks_today = $state_store->ticksToday();
if ($ticks_today >= $config['max_ticks_per_day']) {
    say("Dagcap bereikt ($ticks_today ticks vandaag). Niets gedaan.");
    exit(2);
}

// ─── 5. Zijn we klaar? ───────────────────────────────────────────────────────
if ($state['stanzas'] >= $state['target_stanzas']) {
    say("Bundel is compleet ({$state['stanzas']}/{$state['target_stanzas']} strofen). Niets te doen.");
    exit(2);
}

// ─── 6. Escalatie ────────────────────────────────────────────────────────────
// Een autonoom systeem dat niet weet wanneer het moet ophouden, is een lek.
if ($state['consecutive_failures'] >= $config['max_failures']) {
    say("{$state['consecutive_failures']} mislukkingen op rij. Gestopt — dit vraagt om Lucas.");
    $state_store->journal(
        (int)$state['tick'],
        'ESCALATIE',
        'Loop gepauzeerd na herhaald falen. Verwijder de teller in STATE.json om door te gaan.'
    );
    exit(1);
}

$tick_number = (int)$state['tick'] + 1;
say("── tick $tick_number " . str_repeat('─', 50));

// ─── 7. Prompt samenstellen ──────────────────────────────────────────────────
// Dit is het enige interessante wat de kernel doet: bepalen wat de agent te zien krijgt.
// Niet alles kan mee — het geheugen zit in bestanden, niet in een gespreksgeschiedenis.
$inbox_messages    = $state_store->inbox();
$constitution_text = (string)file_get_contents($config['kernel'] . '/CONSTITUTION.md');

$inbox_text = $inbox_messages === []
    ? '(geen berichten)'
    : implode("\n\n", array_map(
        fn(string $filename, string $contents): string => "### $filename\n$contents",
        array_keys($inbox_messages),
        $inbox_messages
    ));

$base_prompt = <<<TXT
{$state_store->get('MISSION.md')}

---

# Werkregels (constitutie)

{$constitution_text}

---

# Waar we staan

```json
{$state_store->get('STATE.json')}
```

# Backlog

{$state_store->get('BACKLOG.md')}

# Playbook

{$state_store->get('PLAYBOOK.md')}

# Laatste ticks

{$state_store->recentJournal($config['journal_window'])}

# Berichten van Lucas

{$inbox_text}

---

# Jouw opdracht deze tick

Voer één stap uit: schrijf de eerstvolgende strofe.

Antwoord met uitsluitend geldige JSON, zonder omhullende tekst, in deze vorm:

{
  "stanza_index": <nummer van deze strofe>,
  "moment": "<ochtend|middag|avond|nacht>",
  "archetype": "<korte naam van de ervaring, bv. 'lege ochtendstraat'>",
  "stanza": "<de regels, gescheiden door \\n>",
  "journal": "<wat je deed en waarom, twee of drie zinnen>",
  "backlog": ["<eerstvolgende stap>", "..."]
}

Je declareert `moment` en `archetype` zelf. Daarop wordt getoetst: twee strofen met
hetzelfde archetype worden afgekeurd, en de bundel moet van ochtend naar nacht lopen.
TXT;

if ($show_prompt) {
    say("\n── prompt " . str_repeat('─', 48) . "\n$base_prompt\n" . str_repeat('─', 58) . "\n");
}

// ─── 8. De agent, met beperkt aantal pogingen ────────────────────────────────
if ($is_dry_run) {
    $agent = new DryRunAgent($config, $state, $agent_mode);
} else {
    $env_values = read_env_file($config['root'] . '/.env');
    if (empty($env_values['AISTUDIO_KEY'])) {
        fail('AISTUDIO_KEY ontbreekt in ' . $config['root'] . '/.env');
    }
    if ($model_override !== null) {
        $config['aistudio']['model'] = $model_override;
    }
    $agent = new AiStudioAgent(
        $config,
        $env_values['AISTUDIO_URL'] ?? $config['aistudio']['url'],
        $env_values['AISTUDIO_KEY'],
    );
    say('  agent: ai-studio (' . $config['aistudio']['model'] . ')');
}

$max_attempts     = $config['max_retries'] + 1;
$unavailable_reason = null;
$accepted_payload = null;
$gate_errors      = [];
$token_usage      = ['input_tokens' => 0, 'output_tokens' => 0];

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    $attempt_prompt = $gate_errors === []
        ? $base_prompt
        : $base_prompt . "\n\n# Je vorige poging is afgekeurd\n\n- " . implode("\n- ", $gate_errors)
                       . "\n\nLos dit op en lever opnieuw.";

    try {
        $agent_result = $agent->run($attempt_prompt);
    } catch (AgentUnavailable $unavailable) {
        // Niet nogmaals proberen: bij een lege provider of een netwerkstoring
        // levert een tweede poging alleen een tweede timeout op.
        $unavailable_reason = $unavailable->getMessage();
        say("  agent onbereikbaar: $unavailable_reason");
        break;
    }
    $token_usage = $agent_result['usage'];

    // markdown-fences eromheen zijn een klassieker
    $raw_answer = trim($agent_result['raw']);
    $raw_answer = (string)preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw_answer);

    $decoded_answer = json_decode($raw_answer, true);
    if (!is_array($decoded_answer)) {
        $gate_errors = ['het antwoord was geen geldige JSON'];
        say("  poging $attempt: onleesbaar antwoord");
        continue;
    }

    $gate_errors = $gate_checker->check($decoded_answer, $state);
    if ($gate_errors === []) {
        $accepted_payload = $decoded_answer;
        say("  poging $attempt: gates groen");
        break;
    }
    say("  poging $attempt: afgekeurd — " . implode('; ', $gate_errors));
}

// ─── 9. Uitkomst verwerken ───────────────────────────────────────────────────
$poem_before_tick = (string)@file_get_contents($config['artifact']);

if ($unavailable_reason !== null) {
    // Geen tick gedaan: het tick-nummer loopt niet op en de faalteller blijft
    // staan. Wel vastleggen, zodat je in metrics.csv ziet hoe vaak dit gebeurde.
    $state_store->journal(
        $tick_number,
        'UNAVAILABLE',
        "Tick niet uitgevoerd: $unavailable_reason\n\n"
        . 'De stand is ongewijzigd; de volgende tick probeert dezelfde stap opnieuw.'
    );
    $state_store->metric([
        'tick'      => $tick_number,
        'timestamp' => date('c'),
        'outcome'   => 'UNAVAILABLE',
        'stanzas'   => $state['stanzas'],
        'note'      => $unavailable_reason,
    ]);
    say('  UNAVAILABLE — stand ongewijzigd, niets gecommit');
    flock($lock_handle, LOCK_UN);
    exit(2);
}

if ($accepted_payload !== null) {
    // Accepteren: strofe aan het gedicht toevoegen
    $stanza_block = sprintf(
        "\n## %d. %s — %s\n\n%s\n",
        $accepted_payload['stanza_index'],
        ucfirst((string)$accepted_payload['archetype']),
        $accepted_payload['moment'],
        trim((string)$accepted_payload['stanza'])
    );
    file_put_contents($config['artifact'], $stanza_block, FILE_APPEND | LOCK_EX);

    $state['stanzas']              = (int)$accepted_payload['stanza_index'];
    $state['used_archetypes'][]    = (string)$accepted_payload['archetype'];
    $state['last_moment_index']    = (int)array_search(
        mb_strtolower((string)$accepted_payload['moment']),
        $config['moments'],
        true
    );
    $state['consecutive_failures'] = 0;
    $state['last_outcome']         = 'accepted';
    $state['status']               = $state['stanzas'] >= $state['target_stanzas'] ? 'compleet' : 'bezig';

    if (!empty($accepted_payload['backlog']) && is_array($accepted_payload['backlog'])) {
        $backlog_items = implode("\n", array_map(
            fn(string $backlog_item): string => "1. $backlog_item",
            $accepted_payload['backlog']
        ));
        $state_store->put(
            'BACKLOG.md',
            "# Backlog\n\nBovenste item is de eerstvolgende stap. juno onderhoudt deze lijst zelf.\n\n"
            . "## Open\n\n$backlog_items\n\n## Afgerond\n\n_(zie JOURNAL.md)_\n"
        );
    }

    $outcome       = 'ACCEPTED';
    $metric_note   = (string)$accepted_payload['archetype'];
    $journal_entry = (string)$accepted_payload['journal'];
} else {
    // Verwerpen: het werkstuk terug in de tijd, de leergeschiedenis niet.
    exec('git -C ' . escapeshellarg($config['root']) . ' checkout -- work/ 2>&1', $rollback_output, $rollback_exit_code);
    $rollback_confirmed = ((string)@file_get_contents($config['artifact']) === $poem_before_tick);

    $state['consecutive_failures'] = (int)$state['consecutive_failures'] + 1;
    $state['last_outcome']         = 'rejected';
    $state['status']               = 'bezig';

    $outcome       = 'REJECTED';
    $metric_note   = implode('; ', $gate_errors);
    $journal_entry = "Afgekeurd na $max_attempts pogingen. Reden(en): " . implode('; ', $gate_errors)
                   . "\n\nHet werkstuk is teruggedraaid"
                   . ($rollback_confirmed ? '' : ' (LET OP: rollback onbevestigd)')
                   . '. Deze entry blijft staan, zodat de volgende tick niet dezelfde fout maakt.';
}

// ─── 10. Vastleggen — altijd, ongeacht de uitkomst ───────────────────────────
$state['tick'] = $tick_number;
$state_store->write($state);
$state_store->journal($tick_number, $outcome, $journal_entry);
$state_store->metric([
    'tick'          => $tick_number,
    'timestamp'     => date('c'),
    'outcome'       => $outcome,
    'stanzas'       => $state['stanzas'],
    'words'         => str_word_count(strip_tags((string)@file_get_contents($config['artifact']))),
    'judge_score'   => '',
    'input_tokens'  => $token_usage['input_tokens'] ?? 0,
    'output_tokens' => $token_usage['output_tokens'] ?? 0,
    'note'          => $metric_note,
]);

// ─── 11. Commit ──────────────────────────────────────────────────────────────
// Padspecifiek: de agent commit nooit de kernel.
exec('git -C ' . escapeshellarg($config['root']) . ' add mission/ work/ 2>&1');
$commit_message = sprintf(
    'tick %d: %s — %s',
    $tick_number,
    strtolower($outcome),
    $metric_note === '' ? 'geen bijzonderheden' : $metric_note
);
exec(
    'git -C ' . escapeshellarg($config['root']) . ' commit -q -m ' . escapeshellarg($commit_message) . ' 2>&1',
    $commit_output,
    $commit_exit_code
);

say(sprintf('  %s — %d/%d strofen', $outcome, $state['stanzas'], $state['target_stanzas']));
say('  ' . ($commit_exit_code === 0 ? 'gecommit' : 'niets te committen'));

flock($lock_handle, LOCK_UN);
exit(0);
