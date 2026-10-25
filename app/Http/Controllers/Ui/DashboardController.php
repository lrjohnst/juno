<?php

namespace App\Http\Controllers\Ui;

use App\Enums\TickEventLabel;
use App\Exceptions\InvalidJunoTransition;
use App\Http\Controllers\Controller;
use App\Juno;
use App\Models\Dispatch;
use App\Models\DispatchResult;
use App\Models\JunoEvent;
use App\Models\Tick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $paused = Juno::isPaused();
        $openTick = Tick::whereStatus(...TickEventLabel::open())->with('latestEvent')->first();
        $interval = max(1, (int) config('juno.tick_interval'));

        // Zelfde rekenwijze als de scheduler: elke tick_interval minuten, geteld vanaf de Unix-epoch.
        $nextTick = $paused || $openTick
            ? null
            : Carbon::createFromTimestamp((intdiv(intdiv(time(), 60), $interval) + 1) * $interval * 60);

        return view('dashboard', [
            'paused' => $paused,
            'head' => Tick::head()?->load('latestEvent'),
            'nextRole' => Tick::nextRole(Tick::head()),
            'openTick' => $openTick,
            'nextTick' => $nextTick,
            'ticks' => Tick::with('latestEvent')->withCount(['dispatches', 'journalEntries'])->latest('id')->limit(10)->get(),
            'junoEvents' => JunoEvent::latest('happened_at')->latest('id')->limit(5)->get(),
            'dispatches' => Dispatch::with(['latestEvent', 'result'])->latest('id')->limit(5)->get(),
            'tickCount' => Tick::count(),
            'dispatchCount' => Dispatch::count(),
            'costToday' => (float) DispatchResult::where('created_at', '>=', now()->startOfDay())->sum('cost_usd'),
        ]);
    }

    public function pause(Request $request): RedirectResponse
    {
        return $this->transition(fn (string $description) => Juno::pause($description), $request, 'Juno is gepauzeerd: er worden geen ticks klaargezet of geclaimd.');
    }

    public function resume(Request $request): RedirectResponse
    {
        return $this->transition(fn (string $description) => Juno::resume($description), $request, 'Juno is hervat.');
    }

    private function transition(callable $action, Request $request, string $message): RedirectResponse
    {
        $description = $request->validate(['description' => ['required', 'string', 'max:1000']])['description'];

        try {
            $action($description);
        } catch (InvalidJunoTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
