<?php

namespace App\Http\Controllers\Ui;

use App\Enums\TickEventLabel;
use App\Enums\TickRole;
use App\Exceptions\InvalidTickTransition;
use App\Http\Controllers\Controller;
use App\Juno;
use App\Models\Tick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TickController extends Controller
{
    public function index(Request $request): View
    {
        $role = TickRole::tryFrom((string) $request->query('role'));

        return view('ticks.index', [
            'role' => $role,
            'ticks' => Tick::with('latestEvent')
                ->when($role, fn ($query) => $query->where('role', $role))
                ->withCount(['dispatches', 'journalEntries', 'documentVersions', 'children'])
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function show(Tick $tick): View
    {
        $status = $tick->getStatus();
        $openTick = Tick::whereStatus(...TickEventLabel::open())->first();
        $paused = Juno::isPaused();

        // Waarom een rollback naar deze tick (nog) niet kan; null = het kan.
        $rollbackBlocker = match (true) {
            $status?->label !== TickEventLabel::Finished => 'Alleen naar een tick die Finished is.',
            ! $paused => 'Pauzeer Juno eerst.',
            $openTick !== null => "Tick {$openTick->id} staat nog open: laat die aflopen, annuleer of geef hem op.",
            default => null,
        };

        return view('ticks.show', [
            'tick' => $tick->load(['events' => fn ($q) => $q->orderBy('happened_at')->orderBy('id'), 'parent.latestEvent', 'children.latestEvent']),
            'status' => $status,
            'ancestors' => $tick->getAncestors()->skip(1)->take(15),
            'dispatches' => $tick->dispatches()->with(['latestEvent', 'result'])->orderBy('id')->get(),
            'journalEntries' => $tick->journalEntries()->orderBy('id')->get(),
            'documentVersions' => $tick->documentVersions()->with('document')->orderBy('id')->get(),
            'rollbackBlocker' => $rollbackBlocker,
        ]);
    }

    /**
     * Zet nu een tick klaar, los van het interval (zelfde regels als de scheduler).
     */
    public function store(Request $request): RedirectResponse
    {
        $role = $request->validate(['role' => ['nullable', Rule::enum(TickRole::class)]])['role'] ?? null;
        $tick = Tick::schedule($role ? TickRole::from($role) : null);

        if (! $tick) {
            return back()->with('error', Juno::isPaused() ? 'Juno is gepauzeerd.' : 'Er staat al een tick open.');
        }

        return redirect()->route('ticks.show', $tick)->with('success', "Tick {$tick->id} ({$tick->role->value}) staat klaar; de kernel pakt hem binnen een minuut op.");
    }

    public function cancel(Request $request, Tick $tick): RedirectResponse
    {
        return $this->setStatus($request, $tick, TickEventLabel::Canceled);
    }

    public function abandon(Request $request, Tick $tick): RedirectResponse
    {
        return $this->setStatus($request, $tick, TickEventLabel::Abandoned);
    }

    public function rollback(Request $request, Tick $tick): RedirectResponse
    {
        $description = $request->validate(['description' => ['required', 'string', 'max:1000']])['description'];

        try {
            $new = Tick::rollbackTo($tick, $description);
        } catch (InvalidTickTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('ticks.show', $new)->with('success', "Tick {$new->id} staat klaar met tick {$tick->id} als parent; hij start na hervatten.");
    }

    private function setStatus(Request $request, Tick $tick, TickEventLabel $label): RedirectResponse
    {
        $description = $request->validate(['description' => ['required', 'string', 'max:1000']])['description'];

        try {
            $tick->setStatus(['label' => $label, 'description' => $description]);
        } catch (InvalidTickTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Tick {$tick->id} is {$label->value}.");
    }
}
