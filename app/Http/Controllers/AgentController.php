<?php

namespace App\Http\Controllers;

use App\Enums\AgentEventLabel;
use App\Models\Agent;
use App\Models\AgentEmail;
use App\Models\Document;
use App\Models\Tick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Voor de kernel: alles wat hij over de agent van een tick nodig heeft om context en prompt samen te stellen.
 */
class AgentController extends Controller
{
    public function forTick(Tick $tick): JsonResponse
    {
        $agent = Agent::forTick($tick);

        if (! $agent) {
            return response()->json(['message' => "Tick {$tick->id} heeft geen agent (rol: ".($tick->role?->value ?? 'geen').').'], 404);
        }

        return response()->json([
            'agent' => $agent->only(['id', 'name', 'role', 'summary']),
            'role_prompt' => $agent->roleDocument?->getContent($tick),
            'documents' => $agent->documents()->orderBy('name')->pluck('name'),
            'team' => Agent::whereKeyNot($agent->id)->orderBy('id')->get(['name', 'kind', 'role', 'summary']),
            'session_id' => $agent->getSessionId(),
            'tick_number' => $agent->getTickNumber($tick),
            'started_at' => $agent->getStartedAt(),
            'ticks_since_compacted' => $agent->getTicksSince(AgentEventLabel::Compacted, $tick),
            'ticks_since_recapped' => $agent->getTicksSince(AgentEventLabel::Recapped, $tick),
            'new_emails' => $agent->getNewEmails($tick)->map(fn (AgentEmail $email) => $email->present()),
        ]);
    }

    /**
     * {label, description, tick_id}: Compacted, Recapped of SessionReset.
     */
    public function storeEvent(Request $request, Agent $agent): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', Rule::enum(AgentEventLabel::class)],
            'description' => ['nullable', 'string', 'max:65535'],
            'tick_id' => ['nullable', 'integer', 'exists:ticks,id'],
        ]);

        $event = $agent->recordEvent(
            AgentEventLabel::from($data['label']),
            isset($data['tick_id']) ? Tick::find($data['tick_id']) : null,
            $data['description'] ?? null,
        );

        return response()->json($event, 201);
    }
}
