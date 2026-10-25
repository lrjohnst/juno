<?php

namespace App\Enums;

/**
 * Levenscyclus van een API-token: Issued → Revoked.
 */
enum ApiTokenEventLabel: string
{
    case Issued = 'Issued';
    case Revoked = 'Revoked';
}
