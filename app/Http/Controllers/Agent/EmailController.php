<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Berichten tussen teamleden, namens de agent van de tick die loopt.
 */
class EmailController extends Controller
{
    use CurrentAgent;

    /**
     * Berichten van en aan deze agent, nieuwste eerst. ?with=<naam>: alleen met dat teamlid.
     */
    public function index(Request $request): JsonResponse
    {
        [, $agent] = $this->currentAgent();
        $data = $request->validate([
            'with' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $with = isset($data['with']) ? Agent::findByName($data['with']) : null;

        if (isset($data['with']) && ! $with) {
            return response()->json(['message' => "Geen teamlid met de naam {$data['with']}."], 422);
        }

        $emails = AgentEmail::with(['from', 'to'])
            ->where(fn ($q) => $q->where('from_agent_id', $agent->id)->orWhere('to_agent_id', $agent->id))
            ->when($with, fn ($q) => $q->where(fn ($w) => $w->where('from_agent_id', $with->id)->orWhere('to_agent_id', $with->id)))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'agent' => $agent->name,
            'emails' => $emails->getCollection()->map(fn (AgentEmail $email) => $email->present()),
            'meta' => ['page' => $emails->currentPage(), 'last_page' => $emails->lastPage(), 'total' => $emails->total()],
        ]);
    }

    /**
     * {to: <naam>, body}. Hoogstens één bericht per ontvanger per tick: een tweede geeft 409.
     */
    public function store(Request $request): JsonResponse
    {
        [$tick, $agent] = $this->currentAgent();
        $data = $request->validate([
            'to' => ['required', 'string'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
        $to = Agent::findByName($data['to']);

        if (! $to || $to->is($agent)) {
            return response()->json([
                'message' => $to ? 'Je kunt jezelf geen bericht sturen.' : "Geen teamlid met de naam {$data['to']}.",
                'team' => Agent::whereKeyNot($agent->id)->orderBy('id')->pluck('name'),
            ], 422);
        }

        if (AgentEmail::where('tick_id', $tick->id)->where('to_agent_id', $to->id)->exists()) {
            return response()->json(['message' => "Je hebt {$to->name} deze tick al een bericht gestuurd."], 409);
        }

        $email = AgentEmail::create([
            'tick_id' => $tick->id,
            'from_agent_id' => $agent->id,
            'to_agent_id' => $to->id,
            'body' => $data['body'],
        ]);

        return response()->json(['email' => $email->load(['from', 'to'])->present()], 201);
    }
}
