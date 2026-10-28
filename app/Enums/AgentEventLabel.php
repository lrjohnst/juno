<?php

namespace App\Enums;

/**
 * Events van een agent. Geen status: de kernel rekent er het ritme mee uit (compact, recap), en
 * SessionReset laat de volgende dispatch in een nieuwe chat beginnen.
 */
enum AgentEventLabel: string
{
    case SessionReset = 'SessionReset';
    case Compacted = 'Compacted';
    case Recapped = 'Recapped';
}
