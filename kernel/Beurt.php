<?php
declare(strict_types=1);

/**
 * Alles wat een gate over deze tick mag weten.
 *
 * Eén object in plaats van zes losse parameters, zodat je een gate kunt
 * toevoegen zonder elke aanroep aan te passen. Velden zijn null zolang die fase
 * nog niet is bereikt: `antwoord` vóór de agent heeft geantwoord, `na` vóór de
 * hermeting.
 */
final class Beurt
{
    public function __construct(
        public readonly Config $config,
        public readonly int $tick,
        public readonly Meting $voor,
        public ?AgentAntwoord $antwoord = null,
        public ?Meting $na = null,
        public int $poging = 1,
    ) {}
}
