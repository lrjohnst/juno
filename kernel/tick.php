#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * tick.php — één heartbeat van juno.
 *
 * De kernel is met opzet dom: hij stelt een opdracht samen, roept de agent aan,
 * schrijft het resultaat weg, draait de testsuite en legt vast wat er gebeurde.
 * Alle intelligentie zit in de agent; alle betrouwbaarheid hier.
 *
 * Verschil met v1 (de gedichten-agent): de kwaliteitstoets is nu hard. Een tick
 * slaagt alleen als er een test bij groen komt. Daardoor wordt de rollback ook
 * echt gebruikt — in v1 was hij ceremonieel, want daar schreef de kernel pas ná
 * de controle.
 *
 * Gebruik:
 *   php tick.php                     echte tick via ai-studio
 *   php tick.php --dry-run           stub-agent, geen API, geen kosten
 *   php tick.php --dry-run --mode=vorm-fout      forceert een vormafkeuring
 *   php tick.php --dry-run --mode=geen-voortgang forceert een voortgangsafkeuring
 *   php tick.php --verbose           toont de samengestelde opdracht
 *   php tick.php --model=gpt-5.5     ander model dan de standaard
 *
 * Exit codes: 0 = tick gedaan, 1 = fout, 2 = bewust overgeslagen.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/State.php';
require __DIR__ . '/Gates.php';
require __DIR__ . '/TestRunner.php';
require __DIR__ . '/Agent.php';
require __DIR__ . '/AiStudioAgent.php';

$command_options = getopt('', ['dry-run', 'mode::', 'verbose', 'model::']);
$is_dry_run     = isset($command_options['dry-run']);
$agent_mode     = (string) ($command_options['mode'] ?? 'ok');
$show_prompt    = isset($command_options['verbose']);
$model_override = isset($command_options['model']) ? (string) $command_options['model'] : null;

function say(string $message): void { echo $message . "\n"; }
function fail(string $message): never { fwrite(STDERR, "FOUT: $message\n"); exit(1); }

/** De bestanden die de agent tot nu toe heeft geschreven. */
function lees_werkbestanden(array $config): array
{
    $bestanden = [];
    foreach (glob($config['work'] . '/*.php') ?: [] as $pad) {
        $bestanden[basename($pad)] = (string) file_get_contents($pad);
    }
    ksort($bestanden);
    return $bestanden;
}

// ─── 1. Lock ─────────────────────────────────────────────────────────────────
// Cron vuurt op een vast ritme, maar een tick kan langer duren dan dat ritme.
@mkdir($config['var'], 0700, true);
$lock_handle = fopen($config['var'] . '/tick.lock', 'c');
if ($lock_handle === false || ! flock($lock_handle, LOCK_EX | LOCK_NB)) {
    say('Er draait al een tick. Niets gedaan.');
    exit(2);
}

// ─── 2. Kill switch ──────────────────────────────────────────────────────────
if (file_exists($config['var'] . '/STOP')) {
    say('STOP-bestand aanwezig. Niets gedaan.');
    exit(2);
}

// ─── 3. Preflight ────────────────────────────────────────────────────────────
$gate_checker = new Gates($config);
$preflight_problems = $gate_checker->preflight();
if ($preflight_problems !== []) {
    foreach ($preflight_problems as $probleem) {
        fwrite(STDERR, "preflight: $probleem\n");
    }
    exit(1);
}

$state_store = new State($config);
$state       = $state_store->read();
$test_runner = new TestRunner($config);

// ─── 4. Dagcap ───────────────────────────────────────────────────────────────
$ticks_today = $state_store->ticksToday();
if ($ticks_today >= $config['max_ticks_per_day']) {
    say("Dagcap bereikt ($ticks_today ticks vandaag). Niets gedaan.");
    exit(2);
}

// ─── 5. Nulmeting ────────────────────────────────────────────────────────────
// Eerst meten waar we staan. Dit getal is zowel de voortgangsmetriek als de
// drempel waar deze tick boven moet komen.
$voor = $test_runner->run();
say(sprintf('── tick %d · %d/%d tests groen %s',
    (int) $state['tick'] + 1, $voor['groen'], $voor['totaal'], str_repeat('─', 28)));

if ($voor['totaal'] === 0) {
    fail('de testsuite vond geen tests — staat er iets in spec/tests/?');
}

// ─── 6. Zijn we klaar? ───────────────────────────────────────────────────────
if ($voor['groen'] >= $voor['totaal']) {
    say("Alle {$voor['totaal']} tests staan groen. De missie is behaald.");
    exit(2);
}

// ─── 7. Escalatie ────────────────────────────────────────────────────────────
// Een autonoom systeem dat niet weet wanneer het moet ophouden, is een lek.
if ($state['consecutive_failures'] >= $config['max_failures']) {
    say("{$state['consecutive_failures']} mislukkingen op rij. Gestopt — dit vraagt om Lucas.");
    $state_store->journal((int) $state['tick'], 'ESCALATIE',
        'Loop gepauzeerd na herhaald falen. Zet consecutive_failures in STATE.json op 0 om door te gaan.');
    exit(1);
}

$tick_number = (int) $state['tick'] + 1;

// ─── 8. Opdracht samenstellen ────────────────────────────────────────────────
// Het enige interessante wat de kernel doet: bepalen wat de agent te zien krijgt.
// Niet alles kan mee — het geheugen zit in bestanden, niet in een gesprek.
$werkbestanden     = lees_werkbestanden($config);
$constitution_text = (string) file_get_contents($config['kernel'] . '/CONSTITUTION.md');
$faalrapport       = $test_runner->faalrapport($voor, $config['max_fouten_in_prompt']);

$bestanden_text = $werkbestanden === []
    ? '(nog geen bestanden — dit is de eerste tick)'
    : implode("\n\n", array_map(
        fn(string $naam, string $inhoud): string => "### work/$naam\n```php\n$inhoud\n```",
        array_keys($werkbestanden),
        $werkbestanden
    ));

$inbox_messages = $state_store->inbox();
$inbox_text = $inbox_messages === []
    ? '(geen berichten)'
    : implode("\n\n", array_map(
        fn(string $naam, string $inhoud): string => "### $naam\n$inhoud",
        array_keys($inbox_messages),
        $inbox_messages
    ));

$base_prompt = <<<TXT
{$state_store->get('MISSION.md')}

---

# Werkregels

{$constitution_text}

---

# Waar we staan

{$voor['groen']} van de {$voor['totaal']} tests staan groen.

```json
{$state_store->get('STATE.json')}
```

# Je huidige code

{$bestanden_text}

# Falende tests

{$faalrapport}

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

Zorg dat er minstens één test bij groen komt. Lever precies één bestand in — de
volledige nieuwe inhoud, niet een fragment of een patch. Bestaat het bestand al,
dan overschrijf je het; je moet dus alles wat al werkte opnieuw meeleveren.

Een tick waarna er niet meer tests groen staan dan ervoor, wordt afgekeurd en
teruggedraaid.

Antwoord met uitsluitend geldige JSON in deze vorm:

{
  "bestand": "<bestandsnaam, bv. interpreter.php>",
  "inhoud": "<de volledige PHP-broncode>",
  "journal": "<wat je deed en waarom, twee of drie zinnen>",
  "backlog": ["<eerstvolgende stap>", "..."]
}
TXT;

if ($show_prompt) {
    say("\n── opdracht " . str_repeat('─', 46) . "\n$base_prompt\n" . str_repeat('─', 58) . "\n");
}

// ─── 9. De agent, met beperkt aantal pogingen ────────────────────────────────
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
    $agent = new AiStudioAgent($config, $env_values['AISTUDIO_URL'] ?? $config['aistudio']['url'],
                               $env_values['AISTUDIO_KEY']);
    say('  agent: ai-studio (' . $config['aistudio']['model'] . ')');
}

$max_attempts       = $config['max_retries'] + 1;
$geslaagd           = false;
$gate_errors        = [];
$token_usage        = ['input_tokens' => 0, 'output_tokens' => 0];
$unavailable_reason = null;
$na                 = $voor;
$geschreven_bestand = null;
$journal_van_agent  = '';
$backlog_van_agent  = [];

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    $attempt_prompt = $gate_errors === []
        ? $base_prompt
        : $base_prompt . "\n\n# Je vorige poging is afgekeurd\n\n- " . implode("\n- ", $gate_errors)
                       . "\n\nLos dit op en lever opnieuw.";

    try {
        $agent_result = $agent->run($attempt_prompt);
    } catch (AgentUnavailable $unavailable) {
        // Niet nogmaals proberen: bij een leeg tegoed of een netwerkstoring levert
        // een tweede poging alleen een tweede timeout op.
        $unavailable_reason = $unavailable->getMessage();
        say("  agent onbereikbaar: $unavailable_reason");
        break;
    }
    $token_usage = $agent_result['usage'];

    $raw_answer = trim($agent_result['raw']);
    $raw_answer = (string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw_answer);
    $decoded_answer = json_decode($raw_answer, true);

    if (! is_array($decoded_answer)) {
        $gate_errors = ['het antwoord was geen geldige JSON'];
        say("  poging $attempt: onleesbaar antwoord");
        continue;
    }

    $gate_errors = $gate_checker->vorm($decoded_answer);
    if ($gate_errors !== []) {
        say("  poging $attempt: vorm afgekeurd — " . implode('; ', $gate_errors));
        continue;
    }

    // Vorm klopt: wegschrijven en de testsuite laten oordelen.
    $bestandsnaam = (string) $decoded_answer['bestand'];
    file_put_contents($config['work'] . '/' . $bestandsnaam, (string) $decoded_answer['inhoud'], LOCK_EX);
    $na = $test_runner->run();

    $gate_errors = $gate_checker->voortgang($voor['groen'], $na['groen'], $na['totaal']);
    if ($gate_errors === []) {
        $geslaagd           = true;
        $geschreven_bestand = $bestandsnaam;
        $journal_van_agent  = (string) $decoded_answer['journal'];
        $backlog_van_agent  = is_array($decoded_answer['backlog'] ?? null) ? $decoded_answer['backlog'] : [];
        say(sprintf('  poging %d: %d → %d groen, geaccepteerd (work/%s)',
            $attempt, $voor['groen'], $na['groen'], $bestandsnaam));
        break;
    }

    // Afgekeurd: terugdraaien en de agent de echte testuitvoer meegeven. Dit is de
    // terugkoppeling die v1 niet had — een falende test zegt precies wat er mis is.
    exec('git -C ' . escapeshellarg($config['root']) . ' checkout -- work/ 2>&1');
    $gate_errors[] = "Testuitvoer na jouw poging:\n" . $test_runner->faalrapport($na, $config['max_fouten_in_prompt']);
    say(sprintf('  poging %d: %d → %d groen, afgekeurd', $attempt, $voor['groen'], $na['groen']));
}

// ─── 10. Uitkomst verwerken ──────────────────────────────────────────────────
if ($unavailable_reason !== null) {
    // Geen tick gedaan: tick-nummer en faalteller blijven staan. Wel vastleggen,
    // zodat je in metrics.csv ziet hoe vaak dit gebeurde.
    $state_store->journal($tick_number, 'UNAVAILABLE',
        "Tick niet uitgevoerd: $unavailable_reason\n\n"
        . 'De stand is ongewijzigd; de volgende tick probeert dezelfde stap opnieuw.');
    $state_store->metric([
        'tick' => $tick_number, 'timestamp' => date('c'), 'outcome' => 'UNAVAILABLE',
        'groen' => $voor['groen'], 'totaal' => $voor['totaal'], 'note' => $unavailable_reason,
    ]);
    say('  UNAVAILABLE — stand ongewijzigd, niets gecommit');
    flock($lock_handle, LOCK_UN);
    exit(2);
}

if ($geslaagd) {
    $state['consecutive_failures'] = 0;
    $state['last_outcome']         = 'accepted';
    $state['groen']               = $na['groen'];
    $state['totaal']              = $na['totaal'];
    $state['status']              = $na['groen'] >= $na['totaal'] ? 'compleet' : 'bezig';
    $state['bestanden']           = array_keys(lees_werkbestanden($config));

    if ($backlog_van_agent !== []) {
        $items = implode("\n", array_map(fn(string $b): string => "1. $b", $backlog_van_agent));
        $state_store->put('BACKLOG.md',
            "# Backlog\n\nBovenste item is de eerstvolgende stap. juno onderhoudt deze lijst zelf.\n\n"
            . "## Open\n\n$items\n\n## Afgerond\n\n_(zie JOURNAL.md)_\n");
    }

    $outcome       = 'ACCEPTED';
    $metric_note   = sprintf('work/%s · %d→%d', $geschreven_bestand, $voor['groen'], $na['groen']);
    $journal_entry = $journal_van_agent;
} else {
    $state['consecutive_failures'] = (int) $state['consecutive_failures'] + 1;
    $state['last_outcome']         = 'rejected';
    $state['status']               = 'bezig';

    $outcome       = 'REJECTED';
    $metric_note   = implode('; ', array_slice($gate_errors, 0, 1));
    $journal_entry = "Afgekeurd na $max_attempts pogingen. Reden(en): " . implode('; ', $gate_errors)
                   . "\n\nHet werkstuk is teruggedraaid naar de vorige staat. Deze entry blijft staan, "
                   . 'zodat de volgende tick niet dezelfde fout maakt.';
}

// ─── 11. Vastleggen — altijd, ongeacht de uitkomst ───────────────────────────
$state['tick'] = $tick_number;
$state_store->write($state);
$state_store->journal($tick_number, $outcome, $journal_entry);
$state_store->metric([
    'tick'          => $tick_number,
    'timestamp'     => date('c'),
    'outcome'       => $outcome,
    'groen'         => $geslaagd ? $na['groen'] : $voor['groen'],
    'totaal'        => $voor['totaal'],
    'input_tokens'  => $token_usage['input_tokens'] ?? 0,
    'output_tokens' => $token_usage['output_tokens'] ?? 0,
    'note'          => $metric_note,
]);

// ─── 12. Commit ──────────────────────────────────────────────────────────────
// Padspecifiek: de agent commit nooit de kernel of de testsuite.
exec('git -C ' . escapeshellarg($config['root']) . ' add mission/ work/ 2>&1');
$commit_message = sprintf('tick %d: %s — %s', $tick_number, strtolower($outcome),
    $metric_note === '' ? 'geen bijzonderheden' : $metric_note);
exec('git -C ' . escapeshellarg($config['root']) . ' commit -q -m '
     . escapeshellarg($commit_message) . ' 2>&1', $commit_output, $commit_exit_code);

say(sprintf('  %s — %d/%d tests groen', $outcome,
    $geslaagd ? $na['groen'] : $voor['groen'], $voor['totaal']));
say('  ' . ($commit_exit_code === 0 ? 'gecommit' : 'niets te committen'));

flock($lock_handle, LOCK_UN);
exit(0);
