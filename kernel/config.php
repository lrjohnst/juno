<?php
declare(strict_types=1);

/**
 * Configuratie van de kernel. Alle knoppen op één plek.
 * juno kan dit bestand lezen maar niet wijzigen — dat is het hele punt.
 */
return [
    'root'    => '/home/juno',
    'kernel'  => '/home/juno/kernel',
    'mission' => '/home/juno/mission',
    'spec'    => '/home/juno/spec',   // de bevroren testsuite — root-eigendom
    'work'    => '/home/juno/work',   // het werkstuk — hier is de agent vrij
    'var'     => '/home/juno/var',

    'php_binary' => '/usr/bin/php',

    // Harde limieten
    'max_retries'       => 2,    // pogingen per tick voordat we verwerpen
    'max_ticks_per_day' => 60,   // dagcap; boven dit aantal doet de tick niets
    'max_failures'      => 3,    // opeenvolgende mislukkingen -> escalatie naar Lucas
    'timeout_seconds'   => 180,  // harde bovengrens voor een agent-aanroep

    // Wat de agent per tick mag inleveren. Eén bestand per tick houdt de oorzaak
    // van een regressie terugvindbaar; meer bestanden maakt een mislukte tick
    // onleesbaar.
    'max_bestanden_per_tick' => 1,
    'max_bestand_bytes'      => 60000,

    // Toegestane paden binnen work/. Voorkomt dat de agent buiten zijn werkstuk
    // schrijft, of een bestandsnaam verzint die de kernel niet verwacht.
    'bestand_patroon' => '/^[a-z0-9_]+\.php$/',

    // Hoeveel falende tests mee in de prompt. Bij een lege implementatie falen ze
    // allemaal; dan vult één faalrapport de hele prompt met ruis die de agent nog
    // niet kan gebruiken.
    'max_fouten_in_prompt' => 8,

    // Hoeveel journal-entries gaan mee in de prompt
    'journal_window' => 3,

    // ai-studio
    'aistudio' => [
        'url'    => 'https://ai-studio.phitech.cloud/api/chat',
        'prompt' => 'juno-code',
        'model'  => 'gpt-5.5',
    ],
];
