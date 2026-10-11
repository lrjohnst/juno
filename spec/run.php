#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * De bevroren testsuite. Dit is de missie: maak deze tests groen.
 *
 * juno kan hier niet in schrijven (root-eigendom). Dat is het hele punt — wie de
 * test kan aanpassen, haalt niet de test maar de meetlat omlaag.
 *
 * Contract: work/interpreter.php moet een functie juno_eval(string): string
 * definiëren. Elke test geeft broncode in en vergelijkt de uitvoer als string.
 *
 * Uitvoer is regelgebaseerd zodat de kernel hem kan parseren:
 *   OK    <groep>/<naam>
 *   FAIL  <groep>/<naam>  in=<broncode>  verwacht=<x>  kreeg=<y>
 *   TOTAAL <groen>/<totaal>
 *
 * Gebruik:  php spec/run.php [--stil]
 */

$root = dirname(__DIR__);
$stil = in_array('--stil', $argv, true);

$groen = 0;
$totaal = 0;
$fouten = [];

$interpreter = $root . '/work/interpreter.php';
$geladen = false;
if (is_readable($interpreter)) {
    try {
        require_once $interpreter;
        $geladen = function_exists('juno_eval');
    } catch (Throwable $e) {
        $fouten[] = "LAADFOUT  work/interpreter.php gooide: " . $e->getMessage();
    }
}

foreach (glob($root . '/spec/tests/*.php') ?: [] as $bestand) {
    $groep = basename($bestand, '.php');
    $cases = require $bestand;
    foreach ($cases as $case) {
        $totaal++;
        $naam = $case['naam'];
        $in   = $case['in'];
        $uit  = (string) $case['uit'];

        if (! $geladen) {
            $fouten[] = sprintf('FAIL  %s/%s  in=%s  verwacht=%s  kreeg=<juno_eval bestaat niet>',
                $groep, $naam, var_export($in, true), $uit);
            continue;
        }

        try {
            $kreeg = (string) juno_eval($in);
        } catch (Throwable $e) {
            $fouten[] = sprintf('FAIL  %s/%s  in=%s  verwacht=%s  kreeg=<%s: %s>',
                $groep, $naam, var_export($in, true), $uit,
                (new ReflectionClass($e))->getShortName(), $e->getMessage());
            continue;
        }

        if ($kreeg === $uit) {
            $groen++;
            if (! $stil) {
                echo "OK    $groep/$naam\n";
            }
        } else {
            $fouten[] = sprintf('FAIL  %s/%s  in=%s  verwacht=%s  kreeg=%s',
                $groep, $naam, var_export($in, true), $uit, var_export($kreeg, true));
        }
    }
}

foreach ($fouten as $fout) {
    echo $fout . "\n";
}
echo "TOTAAL $groen/$totaal\n";

exit($groen === $totaal ? 0 : 1);
