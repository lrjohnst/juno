<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * De instellingen die hier te wijzigen zijn, met uitleg. Allemaal hele minuten of aantallen.
     */
    public const SETTINGS = [
        'juno.tick_interval' => ['Tick-interval', 'minuten', 'Zo vaak zet de scheduler een tick klaar (geteld vanaf de Unix-epoch).'],
        'juno.tick_abandon_after' => ['Tick opgeven na', 'minuten', 'Een geclaimde tick zonder afsluiting krijgt daarna Abandoned. Ruim boven de dispatch-time-out houden.'],
        'juno.dispatch_timeout' => ['Dispatch-time-out', 'minuten', 'Daarna stopt de kernel een Claude Code-opdracht (TimedOut).'],
        'juno.journal_window' => ['Journal-window', 'ticks', 'Het journal bevat de entries van zoveel laatste ticks, over alle branches heen.'],
    ];

    public function index(): View
    {
        return view('settings.index', [
            'settings' => self::SETTINGS,
            'values' => Setting::pluck('value', 'key'),
            'defaults' => collect(self::SETTINGS)->keys()->mapWithKeys(fn (string $key) => [$key => $this->envDefault($key)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = collect(self::SETTINGS)->keys()->mapWithKeys(fn (string $key) => [str_replace('.', '_', $key) => ['required', 'integer', 'min:1', 'max:10080']])->all();
        $data = $request->validate($rules);

        if ($data['juno_tick_abandon_after'] <= $data['juno_dispatch_timeout']) {
            return back()->withInput()->with('error', 'Tick opgeven na moet groter zijn dan de dispatch-time-out, anders wordt een tick opgegeven terwijl Claude nog draait.');
        }

        foreach (array_keys(self::SETTINGS) as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => (int) $data[str_replace('.', '_', $key)]]);
        }

        return back()->with('success', 'Instellingen opgeslagen; ze gelden vanaf het volgende verzoek en de volgende scheduler-ronde.');
    }

    /**
     * De waarde uit .env / config/juno.php, zonder de rij in settings (die gaat bij het opstarten voor).
     */
    private function envDefault(string $key): mixed
    {
        return (require config_path('juno.php'))[substr($key, strlen('juno.'))] ?? null;
    }
}
