<?php

namespace App\Enums;

/**
 * Levenscyclus van een Claude Code-opdracht: Started → Finished | Failed | TimedOut | Abandoned.
 */
enum DispatchEventLabel: string
{
    case Started = 'Started';
    case Finished = 'Finished';
    case Failed = 'Failed';
    case TimedOut = 'TimedOut';
    case Abandoned = 'Abandoned';

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Started];
    }

    /**
     * Labels die de kernel via de API mag zetten.
     *
     * @return list<self>
     */
    public static function reportable(): array
    {
        return [self::Finished, self::Failed, self::TimedOut];
    }

    public function canFollow(?self $previous): bool
    {
        return match ($this) {
            self::Started => $previous === null,
            self::Finished, self::Failed, self::TimedOut, self::Abandoned => $previous === self::Started,
        };
    }
}
