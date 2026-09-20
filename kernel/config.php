<?php
declare(strict_types=1);

/**
 * Configuratie van de kernel. Alle knoppen op één plek.
 * juno kan dit bestand lezen maar niet wijzigen — dat is het hele punt.
 */
return [
    'root'        => '/home/juno',
    'kernel'      => '/home/juno/kernel',
    'mission'     => '/home/juno/mission',
    'work'        => '/home/juno/work',
    'var'         => '/home/juno/var',

    'artifact'    => '/home/juno/work/GEDICHT.md',

    // Harde limieten
    'max_retries'      => 2,     // pogingen per tick voordat we verwerpen
    'max_ticks_per_day'=> 60,    // dagcap; boven dit aantal doet de tick niets
    'max_failures'     => 3,     // opeenvolgende mislukkingen -> escalatie naar Lucas
    'timeout_seconds'  => 120,   // harde bovengrens voor een agent-aanroep

    // Vorm van een strofe
    'stanza_min_lines' => 3,
    'stanza_max_lines' => 12,

    // Volgorde van de dag — eis 3 uit de missie
    'moments' => ['ochtend', 'middag', 'avond', 'nacht'],

    // Hoeveel journal-entries gaan mee in de prompt
    'journal_window' => 3,

    // ai-studio (fase 2)
    'aistudio' => [
        'url'    => 'https://ai-studio.phitech.cloud/api/chat',
        'prompt' => 'juno-tick',
        'model'  => 'claude-haiku-4-5',
    ],
];
