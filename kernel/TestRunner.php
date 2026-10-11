<?php
declare(strict_types=1);

/**
 * Draait de bevroren testsuite en leest de uitslag.
 *
 * Dit is de kwaliteitsmeting van v2, en hij is hard: hoeveel tests staan groen.
 * Geen beoordelaar, geen cijfer op gevoel. En de agent kan hem niet beïnvloeden —
 * spec/ is root-eigendom, net als de kernel.
 */
final class TestRunner
{
    public function __construct(private array $config) {}

    /**
     * @return array{groen:int, totaal:int, fouten:string[], rauw:string}
     */
    public function run(): array
    {
        $commando = sprintf(
            '%s %s --stil 2>&1',
            escapeshellcmd($this->config['php_binary']),
            escapeshellarg($this->config['spec'] . '/run.php')
        );

        $regels = [];
        exec($commando, $regels);
        $rauw = implode("\n", $regels);

        $groen = 0;
        $totaal = 0;
        $fouten = [];

        foreach ($regels as $regel) {
            if (preg_match('/^TOTAAL (\d+)\/(\d+)$/', trim($regel), $m)) {
                $groen = (int) $m[1];
                $totaal = (int) $m[2];
            } elseif (str_starts_with($regel, 'FAIL') || str_starts_with($regel, 'LAADFOUT')) {
                $fouten[] = trim($regel);
            }
        }

        return ['groen' => $groen, 'totaal' => $totaal, 'fouten' => $fouten, 'rauw' => $rauw];
    }

    /**
     * De falende tests, ingekort voor de prompt.
     *
     * Niet alle veertig meegeven: bij een lege implementatie falen ze allemaal en dan
     * vult één faalrapport de hele prompt. Het eerste groepje is waar de agent aan moet
     * werken; de rest is ruis die hij nog niet kan gebruiken.
     */
    public function faalrapport(array $uitslag, int $maximum): string
    {
        if ($uitslag['fouten'] === []) {
            return 'Alle tests staan groen.';
        }

        $tonen = array_slice($uitslag['fouten'], 0, $maximum);
        $rest = count($uitslag['fouten']) - count($tonen);

        $tekst = implode("\n", $tonen);
        if ($rest > 0) {
            $tekst .= "\n\n(en nog $rest falende tests, die je ziet zodra deze groen staan)";
        }
        return $tekst;
    }
}
