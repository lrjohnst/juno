<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Het journal voor Juno zelf. Schrijven hangt aan de tick die loopt; lezen kan altijd.
 */
class JournalController extends Controller
{
    use CurrentAgent;

    /**
     * Standaard het window (de laatste journal_window ticks). ?all=1: alles, nieuwste eerst, per pagina.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $names = Agent::whereNotNull('role')->pluck('name', 'role');
        $present = fn (JournalEntry $entry) => [
            'id' => $entry->id,
            'tick_id' => $entry->tick_id,
            'agent' => $names[$entry->tick?->role?->value] ?? null,
            'content' => $entry->content,
            'created_at' => $entry->created_at,
        ];

        if (! $request->boolean('all')) {
            return response()->json([
                'journal_window' => (int) config('juno.journal_window'),
                'entries' => JournalEntry::window()->load('tick')->map($present),
            ]);
        }

        $entries = JournalEntry::with('tick')->orderByDesc('id')->paginate($data['per_page'] ?? 100);

        return response()->json([
            'entries' => $entries->getCollection()->map($present),
            'meta' => ['page' => $entries->currentPage(), 'last_page' => $entries->lastPage(), 'total' => $entries->total()],
        ]);
    }

    /**
     * {entries: [{content}, ...]}, bij de tick die loopt.
     */
    public function store(Request $request): JsonResponse
    {
        [$tick] = $this->currentAgent();
        $data = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.content' => ['required', 'string'],
        ]);

        $entries = DB::transaction(
            fn () => collect($data['entries'])->map(fn (array $entry) => $tick->journalEntries()->create(['content' => $entry['content']]))
        );

        return response()->json(['tick_id' => $tick->id, 'ids' => $entries->pluck('id')], 201);
    }
}
