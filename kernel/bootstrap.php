<?php
declare(strict_types=1);

/**
 * Laadt het framework. Geen composer: één autoloader die vier mappen afzoekt.
 *
 * Zet je een nieuwe klasse in kernel/, kernel/gates/, kernel/agents/ of missie/,
 * dan wordt hij gevonden zonder dat je hier iets hoeft aan te passen. Bestandsnaam
 * is klassenaam plus .php.
 */

spl_autoload_register(static function (string $klasse): void {
    $mappen = [
        __DIR__,
        __DIR__ . '/gates',
        __DIR__ . '/agents',
        dirname(__DIR__) . '/missie',
    ];
    foreach ($mappen as $map) {
        $pad = $map . '/' . $klasse . '.php';
        if (is_file($pad)) {
            require_once $pad;
            return;
        }
    }
});

/** Leest een .env met simpele KEY=value regels. */
function lees_env(string $pad): array
{
    $waarden = [];
    foreach (@file($pad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $regel) {
        $regel = trim($regel);
        if ($regel === '' || str_starts_with($regel, '#') || ! str_contains($regel, '=')) {
            continue;
        }
        [$sleutel, $waarde] = explode('=', $regel, 2);
        $waarden[trim($sleutel)] = trim($waarde, " \t\"'");
    }
    return $waarden;
}
