<?php

namespace App\Http\Controllers\Ui;

use App\Enums\AgentKind;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De inbox van Lucas: berichten van en aan de agents. Geen echte mail; die blijft voor storingen.
 */
class InboxController extends Controller
{
    /**
     * ?view=all: al het berichtenverkeer van het team, niet alleen dat van Lucas.
     */
    public function index(Request $request): View
    {
        $lucas = self::lucas();
        $all = $request->query('view') === 'all';

        return view('inbox.index', [
            'all' => $all,
            'lucas' => $lucas,
            'recipients' => Agent::where('kind', AgentKind::Agent)->orderBy('id')->get(),
            'emails' => AgentEmail::with(['from', 'to'])
                ->unless($all, fn ($q) => $q->where(fn ($w) => $w->where('from_agent_id', $lucas->id)->orWhere('to_agent_id', $lucas->id)))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    /**
     * Een bericht van Lucas (zonder tick). De agent krijgt het bij zijn volgende tick.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to_agent_id' => ['required', 'integer', 'exists:agents,id'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
        $to = Agent::findOrFail($data['to_agent_id']);

        if ($to->kind !== AgentKind::Agent) {
            return back()->withInput()->with('error', 'Een bericht kan alleen naar een agent.');
        }

        AgentEmail::create(['tick_id' => null, 'from_agent_id' => self::lucas()->id, 'to_agent_id' => $to->id, 'body' => $data['body']]);

        return redirect()->route('inbox.index')->with('success', "Bericht aan {$to->name} verstuurd; hij krijgt het bij zijn volgende tick.");
    }

    private static function lucas(): Agent
    {
        return Agent::where('kind', AgentKind::Human)->orderBy('id')->firstOrFail();
    }
}
