<?php
declare(strict_types=1);

/**
 * De harde voortgangsregel: een tick slaagt alleen als de stand vooruit is.
 *
 * Gelijk blijven is geen voortgang en achteruitgang al helemaal niet. Dit is de
 * enige gate die níét te omzeilen is zolang de agent de meting niet kan
 * beïnvloeden — en dat is precies waarom de meting buiten zijn bereik hoort.
 *
 * Een missie zonder monotone maatstaf laat deze gate weg en regelt het zelf.
 */
final class VoortgangGate implements Gate
{
    public function __construct(private bool $sta_gelijk_toe = false) {}

    public function naam(): string { return 'voortgang'; }
    public function naMeting(): bool { return true; }

    public function keur(Beurt $beurt): array
    {
        if ($beurt->na === null) {
            return [];   // nog niet gemeten; deze gate draait pas na het wegschrijven
        }

        if ($beurt->na->isVooruitgangOp($beurt->voor)) {
            return [];
        }
        if ($beurt->na->isAchteruitgangOp($beurt->voor)) {
            return [new Bevinding($this->naam(), sprintf(
                'achteruitgang: de stand was %s en is nu %s. Je hebt iets gesloopt dat eerder werkte.',
                $beurt->voor, $beurt->na
            ))];
        }
        if ($this->sta_gelijk_toe) {
            return [];
        }
        return [new Bevinding($this->naam(), sprintf(
            'geen voortgang: de stand was %s en is nog steeds %s. '
            . 'Een tick slaagt alleen als de stand vooruit gaat.',
            $beurt->voor, $beurt->na
        ))];
    }
}
