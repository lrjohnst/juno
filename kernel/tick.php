#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * tick.php — één heartbeat.
 *
 * Dunne laag: argumenten lezen, missie en agent samenstellen, Kernel draaien,
 * exitcode teruggeven. Alle logica zit in Kernel; dit bestand hoort kort te
 * blijven.
 *
 * Gebruik:
 *   php tick.php                        echte tick via de gateway
 *   php tick.php --stub                 stub-agent: geen API, geen kosten
 *   php tick.php --stub --modus=stuk    forceer een faaltak van de stub
 *   php tick.php --missie=MijnMissie    andere missieklasse dan de standaard
 *   php tick.php --model=gpt-5.5        ander model
 *   php tick.php --toon-opdracht        print de opdracht en stop (geen aanroep)
 *
 * Exitcodes: 0 = tick gedaan, 1 = gestopt (vraagt een mens), 2 = overgeslagen.
 */

require __DIR__ . '/bootstrap.php';

$opties = getopt('', ['stub', 'modus::', 'missie::', 'model::', 'toon-opdracht']);
$config = Config::standaard();
$state  = new State($config);

// ── missie kiezen ────────────────────────────────────────────────────────────
$env        = lees_env($config->root . '/.env');
$missieNaam = (string) ($opties['missie'] ?? $env['JUNO_MISSIE'] ?? 'VoorbeeldMissie');
if (! class_exists($missieNaam)) {
    fwrite(STDERR, "FOUT: missieklasse '$missieNaam' niet gevonden in missie/\n");
    exit(1);
}
$missie = new $missieNaam($config);
if (! $missie instanceof Missie) {
    fwrite(STDERR, "FOUT: $missieNaam implementeert de Missie-interface niet\n");
    exit(1);
}

// ── agent kiezen ─────────────────────────────────────────────────────────────
if (isset($opties['stub'])) {
    if (! $missie instanceof MetStub) {
        fwrite(STDERR, "FOUT: $missieNaam implementeert MetStub niet, dus --stub kan niet.\n");
        exit(1);
    }
    $agent = new StubAgent($missie->stubAntwoorden(), (string) ($opties['modus'] ?? 'ok'));
} else {
    if (empty($env['AISTUDIO_KEY'])) {
        fwrite(STDERR, "FOUT: AISTUDIO_KEY ontbreekt in {$config->root}/.env\n");
        exit(1);
    }
    $agent = new AiStudioAgent($config, $env['AISTUDIO_KEY'],
                               isset($opties['model']) ? (string) $opties['model'] : null);
}

$kernel = new Kernel($config, $missie, $agent, $state);

// ── alleen de opdracht laten zien ────────────────────────────────────────────
if (isset($opties['toon-opdracht'])) {
    echo $kernel->toonOpdracht() . "\n";
    exit(0);
}

exit($kernel->draai()->exitCode());
