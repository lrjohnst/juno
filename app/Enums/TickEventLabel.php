<?php

namespace App\Enums;

/**
 * Levenscyclus van een tick: Scheduled → Claimed → Finished | Failed | Abandoned,
 * of Scheduled → Canceled.
 */
enum TickEventLabel: string
{
    case Scheduled = 'Scheduled';
    case Claimed = 'Claimed';
    case Finished = 'Finished';
    case Failed = 'Failed';
    case Abandoned = 'Abandoned';
    case Canceled = 'Canceled';

    /**
     * Labels waarmee een tick nog open staat.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Scheduled, self::Claimed];
    }

    /**
     * Labels die tick.php via de API mag zetten.
     *
     * @return list<self>
     */
    public static function reportable(): array
    {
        return [self::Finished, self::Failed];
    }

    public function canFollow(?self $previous): bool
    {
        return match ($this) {
            self::Scheduled => $previous === null,
            self::Claimed, self::Canceled => $previous === self::Scheduled,
            self::Finished, self::Failed, self::Abandoned => $previous === self::Claimed,
        };
    }
}
