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
 *
 * Exit codes: 0 = tick gedaan, 1 = fout, 2 = bewust overgeslagen.
 */

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/State.php';
require __DIR__ . '/Gates.php';
require __DIR__ . '/Agent.php';

$opts    = getopt('', ['dry-run', 'mode::', 'verbose']);
$dryRun  = isset($opts['dry-run']);
$mode    = (string)($opts['mode'] ?? 'ok');
$verbose = isset($opts['verbose']);

function say(string $msg): void { echo $msg . "\n"; }
function fail(string $msg): never { fwrite(STDERR, "FOUT: $msg\n"); exit(1); }

// ─── 1. Lock ─────────────────────────────────────────────────────────────────
// Cron vuurt op een vast ritme, maar een tick kan langer duren dan dat ritme.
// Zonder lock schrijven twee agents tegelijk in dezelfde map.
@mkdir($cfg['var'], 0700, true);
$lock = fopen($cfg['var'] . '/tick.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    say('Er draait al een tick. Niets gedaan.');
    exit(2);
}

// ─── 2. Kill switch ──────────────────────────────────────────────────────────
if (file_exists($cfg['var'] . '/STOP')) {
    say('STOP-bestand aanwezig. Niets gedaan.');
    exit(2);
}

// ─── 3. Preflight ────────────────────────────────────────────────────────────
// Liever hier stoppen met een leesbare melding dan halverwege omvallen.
$gates = new Gates($cfg);
if ($problems = $gates->preflight()) {
    foreach ($problems as $p) { fwrite(STDERR, "preflight: $p\n"); }
    exit(1);
}

$state = new State($cfg);
$s     = $state->read();

// ─── 4. Dagcap ───────────────────────────────────────────────────────────────
$today = $state->ticksToday();
if ($today >= $cfg['max_ticks_per_day']) {
    say("Dagcap bereikt ($today ticks vandaag). Niets gedaan.");
    exit(2);
}

// ─── 5. Zijn we klaar? ───────────────────────────────────────────────────────
if ($s['stanzas'] >= $s['target_stanzas']) {
    say("Bundel is compleet ({$s['stanzas']}/{$s['target_stanzas']} strofen). Niets te doen.");
    exit(2);
}

// ─── 6. Escalatie ────────────────────────────────────────────────────────────
// Een autonoom systeem dat niet weet wanneer het moet ophouden, is een lek.
if ($s['consecutive_failures'] >= $cfg['max_failures']) {
    say("{$s['consecutive_failures']} mislukkingen op rij. Gestopt — dit vraagt om Lucas.");
    $state->journal((int)$s['tick'], 'ESCALATIE', 'Loop gepauzeerd na herhaald falen. Verwijder de teller in STATE.json om door te gaan.');
    exit(1);
}

$tick = (int)$s['tick'] + 1;
say("── tick $tick " . str_repeat('─', 50));

// ─── 7. Prompt samenstellen ──────────────────────────────────────────────────
// Dit is het enige interessante wat de kernel doet: bepalen wat de agent te zien krijgt.
// Niet alles kan mee — het geheugen zit in bestanden, niet in een gespreksgeschiedenis.
$inbox = $state->inbox();
$constitution = (string)file_get_contents($cfg['kernel'] . '/CONSTITUTION.md');

$inboxText = $inbox === []
    ? '(geen berichten)'
    : implode("\n\n", array_map(fn($k, $v) => "### $k\n$v", array_keys($inbox), $inbox));

$prompt = <<<TXT
{$state->get('MISSION.md')}

---

# Werkregels (constitutie)

{$constitution}

---

# Waar we staan

```json
{$state->get('STATE.json')}
```

# Backlog

{$state->get('BACKLOG.md')}

# Playbook

{$state->get('PLAYBOOK.md')}

# Laatste ticks

{$state->recentJournal($cfg['journal_window'])}

# Berichten van Lucas

{$inboxText}

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

if ($verbose) {
    say("\n── prompt " . str_repeat('─', 48) . "\n$prompt\n" . str_repeat('─', 58) . "\n");
}

// ─── 8. De agent, met beperkt aantal pogingen ────────────────────────────────
if (!$dryRun) {
    fail('alleen --dry-run is nu ondersteund; AiStudioAgent volgt in fase 2');
}

$agent    = new DryRunAgent($cfg, $s, $mode);
$attempts = $cfg['max_retries'] + 1;
$payload  = null;
$errors   = [];
$usage    = ['input_tokens' => 0, 'output_tokens' => 0];

for ($try = 1; $try <= $attempts; $try++) {
    $attemptPrompt = $errors === []
        ? $prompt
        : $prompt . "\n\n# Je vorige poging is afgekeurd\n\n- " . implode("\n- ", $errors)
                  . "\n\nLos dit op en lever opnieuw.";

    $result = $agent->run($attemptPrompt);
    $usage  = $result['usage'];

    // markdown-fences eromheen zijn een klassieker
    $raw = trim($result['raw']);
    $raw = (string)preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw);

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $errors = ['het antwoord was geen geldige JSON'];
        say("  poging $try: onleesbaar antwoord");
        continue;
    }

    $errors = $gates->check($decoded, $s);
    if ($errors === []) {
        $payload = $decoded;
        say("  poging $try: gates groen");
        break;
    }
    say("  poging $try: afgekeurd — " . implode('; ', $errors));
}

// ─── 9. Uitkomst verwerken ───────────────────────────────────────────────────
$artifactBefore = (string)@file_get_contents($cfg['artifact']);

if ($payload !== null) {
    // Accepteren: strofe aan het gedicht toevoegen
    $block = sprintf(
        "\n## %d. %s — %s\n\n%s\n",
        $payload['stanza_index'],
        ucfirst((string)$payload['archetype']),
        $payload['moment'],
        trim((string)$payload['stanza'])
    );
    file_put_contents($cfg['artifact'], $block, FILE_APPEND | LOCK_EX);

    $s['stanzas']              = (int)$payload['stanza_index'];
    $s['used_archetypes'][]    = (string)$payload['archetype'];
    $s['last_moment_index']    = (int)array_search(mb_strtolower((string)$payload['moment']), $cfg['moments'], true);
    $s['consecutive_failures'] = 0;
    $s['last_outcome']         = 'accepted';
    $s['status']               = $s['stanzas'] >= $s['target_stanzas'] ? 'compleet' : 'bezig';

    if (!empty($payload['backlog']) && is_array($payload['backlog'])) {
        $items = implode("\n", array_map(fn($b) => "1. $b", $payload['backlog']));
        $state->put('BACKLOG.md', "# Backlog\n\nBovenste item is de eerstvolgende stap. juno onderhoudt deze lijst zelf.\n\n## Open\n\n$items\n\n## Afgerond\n\n_(zie JOURNAL.md)_\n");
    }

    $outcome = 'ACCEPTED';
    $note    = (string)$payload['archetype'];
    $journal = (string)$payload['journal'];
} else {
    // Verwerpen: het werkstuk terug in de tijd, de leergeschiedenis niet.
    exec('git -C ' . escapeshellarg($cfg['root']) . ' checkout -- work/ 2>&1', $out, $rc);
    $rolledBack = ((string)@file_get_contents($cfg['artifact']) === $artifactBefore);

    $s['consecutive_failures'] = (int)$s['consecutive_failures'] + 1;
    $s['last_outcome']         = 'rejected';
    $s['status']               = 'bezig';

    $outcome = 'REJECTED';
    $note    = implode('; ', $errors);
    $journal = "Afgekeurd na $attempts pogingen. Reden(en): " . implode('; ', $errors)
             . "\n\nHet werkstuk is teruggedraaid" . ($rolledBack ? '' : ' (LET OP: rollback onbevestigd)')
             . '. Deze entry blijft staan, zodat de volgende tick niet dezelfde fout maakt.';
}

// ─── 10. Vastleggen — altijd, ongeacht de uitkomst ───────────────────────────
$s['tick'] = $tick;
$state->write($s);
$state->journal($tick, $outcome, $journal);
$state->metric([
    'tick'          => $tick,
    'timestamp'     => date('c'),
    'outcome'       => $outcome,
    'stanzas'       => $s['stanzas'],
    'words'         => str_word_count(strip_tags((string)@file_get_contents($cfg['artifact']))),
    'judge_score'   => '',
    'input_tokens'  => $usage['input_tokens'] ?? 0,
    'output_tokens' => $usage['output_tokens'] ?? 0,
    'note'          => $note,
]);

// ─── 11. Commit ──────────────────────────────────────────────────────────────
// Padspecifiek: de agent commit nooit de kernel.
exec('git -C ' . escapeshellarg($cfg['root']) . ' add mission/ work/ 2>&1');
$msg = sprintf('tick %d: %s — %s', $tick, strtolower($outcome), $note === '' ? 'geen bijzonderheden' : $note);
exec('git -C ' . escapeshellarg($cfg['root']) . ' commit -q -m ' . escapeshellarg($msg) . ' 2>&1', $o, $rc);

say(sprintf('  %s — %d/%d strofen', $outcome, $s['stanzas'], $s['target_stanzas']));
say('  ' . ($rc === 0 ? 'gecommit' : 'niets te committen'));

flock($lock, LOCK_UN);
exit(0);
