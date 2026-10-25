<?php

namespace App\Enums;

/**
 * De rol van een tick, in de volgorde van Kolbs leercyclus.
 */
enum TickRole: string
{
    case Abstraction = 'Abstraction';
    case Experimentation = 'Experimentation';
    case Doing = 'Doing';
    case Reflection = 'Reflection';

    /**
     * De rol na deze in de cyclus; na Reflection weer Abstraction.
     */
    public function next(): self
    {
        $cases = self::cases();

        return $cases[(array_search($this, $cases, true) + 1) % count($cases)];
    }
}
