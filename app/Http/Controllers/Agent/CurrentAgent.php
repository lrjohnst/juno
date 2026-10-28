<?php

namespace App\Http\Controllers\Agent;

use App\Models\Agent;
use App\Models\Tick;

/**
 * De agent-API werkt namens de agent van de tick die loopt (Claimed). Zo kan Juno geen bericht of
 * journal-entry schrijven namens een ander, en hoeft hij zelf geen tick of afzender mee te geven.
 */
trait CurrentAgent
{
    /**
     * @return array{0: Tick, 1: Agent}
     */
    protected function currentAgent(): array
    {
        $tick = Tick::current();
        $agent = Agent::forTick($tick);

        if (! $tick || ! $agent) {
            abort(response()->json(['message' => 'Er loopt geen tick met een agent; dit kan alleen tijdens een tick.'], 409));
        }

        return [$tick, $agent];
    }
}
