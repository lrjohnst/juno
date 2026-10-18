<?php
declare(strict_types=1);

/**
 * De agent mag niet bij zijn eigen regels kunnen.
 *
 * Dit is geen voorzichtigheid maar noodzaak: wie de controles kan aanpassen,
 * verlaagt bij de eerste afkeuring gewoon de lat. Ook de leesbaarheidscheck
 * hoort hier — een editor die een bestand herschrijft laat het eigendom
 * springen, en dan valt de tick halverwege om in plaats van hier met een
 * leesbare melding.
 *
 * Geef `beschermdePaden` mee met wat jouw missie ook nog dicht wil hebben,
 * bijvoorbeeld een map met testgevallen of referentiedata.
 */
final class RechtenGate implements Gate
{
    /**
     * @param string[] $leesbaar       paden die leesbaar moeten zijn (absoluut)
     * @param string[] $nietSchrijfbaar paden waar de agent niet in mag schrijven
     */
    public function __construct(
        private array $leesbaar = [],
        private array $nietSchrijfbaar = [],
    ) {}

    public function naam(): string { return 'rechten'; }
    public function naMeting(): bool { return false; }

    public function keur(Beurt $beurt): array
    {
        $bevindingen = [];

        foreach ($this->leesbaar as $pad) {
            if (! is_readable($pad)) {
                $bevindingen[] = Bevinding::fataal($this->naam(),
                    "$pad is niet leesbaar (eigendom gesprongen? gebruik setfacl, geen chmod)");
            }
        }

        foreach ($this->nietSchrijfbaar as $pad) {
            // LET OP: is_writable() gebruikt access(2) en kijkt bij ACL's naar de
            // mask. Het kan schrijfbaar melden terwijl de echte operatie wordt
            // geweigerd, en omgekeerd. Daarom hier een echte poging.
            $proef = rtrim($pad, '/') . '/.schrijfproef';
            $gelukt = @touch($proef);
            if ($gelukt) {
                @unlink($proef);
                $bevindingen[] = Bevinding::fataal($this->naam(),
                    "$pad is schrijfbaar voor de draaiende gebruiker — dan kan de agent "
                    . 'de meetlat verzetten in plaats van de opdracht halen');
            }
        }

        return $bevindingen;
    }
}
