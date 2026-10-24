<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\Tick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'journal_window' => (int) config('juno.journal_window'),
            'entries' => JournalEntry::window()->map->only(['id', 'tick_id', 'content', 'created_at']),
        ]);
    }

    public function store(Request $request, Tick $tick): JsonResponse
    {
        $data = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.content' => ['required', 'string'],
        ]);

        $entries = DB::transaction(
            fn () => collect($data['entries'])->map(fn (array $entry) => $tick->journalEntries()->create(['content' => $entry['content']]))
        );

        return response()->json(['ids' => $entries->pluck('id')], 201);
    }
}
