<?php

namespace App\Enums;

/**
 * Events van Juno als geheel, niet van één tick.
 */
enum JunoEventLabel: string
{
    case Paused = 'Paused';
    case Resumed = 'Resumed';

    /**
     * Labels die samen bepalen of Juno gepauzeerd is.
     *
     * @return list<self>
     */
    public static function pause(): array
    {
        return [self::Paused, self::Resumed];
    }
}
