<?php

namespace App;

use App\Enums\JunoEventLabel;
use App\Exceptions\InvalidJunoTransition;
use App\Models\JunoEvent;

/**
 * Juno als geheel. De toestand staat niet in een kolom maar in juno_events: het laatste
 * pauze-event (op happened_at, daarna id) bepaalt of Juno gepauzeerd is.
 */
final class Juno
{
    public static function isPaused(): bool
    {
        return JunoEvent::whereIn('label', JunoEventLabel::pause())
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->first()?->label === JunoEventLabel::Paused;
    }

    /**
     * @throws InvalidJunoTransition
     */
    public static function pause(string $description): JunoEvent
    {
        if (self::isPaused()) {
            throw new InvalidJunoTransition('Juno is al gepauzeerd.');
        }

        return self::record(JunoEventLabel::Paused, $description);
    }

    /**
     * @throws InvalidJunoTransition
     */
    public static function resume(string $description): JunoEvent
    {
        if (! self::isPaused()) {
            throw new InvalidJunoTransition('Juno is niet gepauzeerd.');
        }

        return self::record(JunoEventLabel::Resumed, $description);
    }

    private static function record(JunoEventLabel $label, string $description): JunoEvent
    {
        return JunoEvent::create([
            'label' => $label,
            'description' => $description,
            'happened_at' => now(),
        ]);
    }
}
