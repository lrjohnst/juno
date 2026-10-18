<?php
declare(strict_types=1);

/**
 * Hoe een tick is afgelopen.
 *
 * Eén plek voor alle uitkomsten, zodat een typo een fout wordt in plaats van een
 * stille tak. De exitcode hangt eraan vast: cron en systemd lezen die.
 */
enum Uitkomst: string
{
    case Geaccepteerd = 'ACCEPTED';    // werk geleverd en goedgekeurd
    case Afgekeurd    = 'REJECTED';    // werk geleverd, maar niet goed genoeg
    case Onbereikbaar = 'UNAVAILABLE'; // agent niet bereikt — géén schuld van de agent
    case Overgeslagen = 'SKIPPED';     // lock, noodstop, dagcap, of missie al klaar
    case Gestopt      = 'HALTED';      // escalatie of preflight: dit vraagt een mens

    public function exitCode(): int
    {
        return match ($this) {
            self::Geaccepteerd, self::Afgekeurd => 0,
            self::Overgeslagen                  => 2,
            self::Onbereikbaar                  => 2,
            self::Gestopt                       => 1,
        };
    }

    /** Verhoogt deze uitkomst de faalteller die naar escalatie leidt? */
    public function teltAlsMislukking(): bool
    {
        return $this === self::Afgekeurd;
    }
}
