<?php

namespace App\Http\Controllers\Ui;

use App\Enums\AgentEventLabel;
use App\Enums\AgentKind;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentEmail;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $agents = Agent::with('roleDocument')->orderBy('id')->get();

        return view('team.index', [
            'agents' => $agents,
            'stats' => $agents->mapWithKeys(fn (Agent $agent) => [$agent->id => self::stats($agent)]),
        ]);
    }

    public function show(Agent $agent): View
    {
        return view('team.show', [
            'agent' => $agent->load(['roleDocument', 'documents']),
            'stats' => self::stats($agent),
            'documents' => Document::orderBy('name')->get(),
            'events' => $agent->events()->orderByDesc('happened_at')->orderByDesc('id')->limit(50)->get(),
            'dispatches' => $agent->dispatches()->with(['latestEvent', 'result'])->latest('id')->limit(20)->get(),
            'emails' => AgentEmail::with(['from', 'to'])
                ->where(fn ($q) => $q->where('from_agent_id', $agent->id)->orWhere('to_agent_id', $agent->id))
                ->latest('id')->limit(20)->get(),
        ]);
    }

    /**
     * Samenvatting (voor het teamoverzicht in de recap) en de documenten die de kernel klaarzet.
     */
    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['integer', 'exists:documents,id'],
        ]);

        $agent->update(['summary' => $data['summary'] ?? null]);
        if ($agent->kind === AgentKind::Agent) {
            $agent->documents()->sync($data['documents'] ?? []);
        }

        return back()->with('success', "{$agent->name} bijgewerkt.");
    }

    /**
     * Nieuwe chat: de volgende dispatch van deze agent begint zonder --resume.
     */
    public function resetSession(Request $request, Agent $agent): RedirectResponse
    {
        $description = $request->validate(['description' => ['required', 'string', 'max:2000']])['description'];
        $agent->recordEvent(AgentEventLabel::SessionReset, null, $description);

        return back()->with('success', "{$agent->name} begint bij de volgende tick een nieuwe chat.");
    }

    /**
     * Ritme en chat van een agent, gerekend vanaf zijn laatste tick.
     */
    private static function stats(Agent $agent): array
    {
        if ($agent->kind !== AgentKind::Agent) {
            return ['ticks' => 0, 'lastTick' => null, 'sessionId' => null, 'sinceCompacted' => 0, 'sinceRecapped' => 0, 'startedAt' => null];
        }

        $lastTick = $agent->ticks()->with('latestEvent')->latest('id')->first();

        return [
            'ticks' => $agent->ticks()->count(),
            'lastTick' => $lastTick,
            'sessionId' => $agent->getSessionId(),
            'sinceCompacted' => $lastTick ? $agent->getTicksSince(AgentEventLabel::Compacted, $lastTick) : 0,
            'sinceRecapped' => $lastTick ? $agent->getTicksSince(AgentEventLabel::Recapped, $lastTick) : 0,
            'startedAt' => $agent->getStartedAt(),
        ];
    }
}
