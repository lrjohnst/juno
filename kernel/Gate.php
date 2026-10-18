<?php
declare(strict_types=1);

/**
 * Een controle. Deterministisch, goedkoop, en niet om te praten.
 *
 * Gates zijn het enige dat de kernel tegen de agent beschermt. Houd ze hard:
 * de beste gate is een vergelijking, geen oordeel. Heb je een taalmodel nodig om
 * te beslissen, dan is het traag, duur en te overtuigen.
 *
 * Een gate die op een leeg antwoord moet werken (preflight) krijgt null binnen.
 */
interface Gate
{
    /** @return Bevinding[] leeg betekent: akkoord */
    public function keur(Beurt $beurt): array;

    public function naam(): string;

    /**
     * Draait deze gate pas ná het wegschrijven en de hermeting?
     *
     * false = vóór het wegschrijven, op het antwoord zelf. Goedkoop, want een
     *         afkeuring kost dan geen schrijfactie en geen rollback.
     * true  = na de hermeting, als je de nieuwe stand nodig hebt.
     */
    public function naMeting(): bool;
}
