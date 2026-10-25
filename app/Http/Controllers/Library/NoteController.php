<?php

namespace App\Http\Controllers\Library;

use App\Enums\SourceNoteKind;
use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\SourceNote;
use App\Models\Tick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Aantekeningen bij bronnen, per TF-onderwerp. De inhoud heeft versies; een nieuwe versie hangt aan de tick
 * die loopt (door de server ingevuld).
 */
class NoteController extends Controller
{
    /**
     * ?topic=, ?kind=, ?source= ; elke aantekening met de laatste versie van de inhoud.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topic' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', Rule::enum(SourceNoteKind::class)],
            'source' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $notes = SourceNote::with(['latestVersion', 'source:id,title,authors,year'])->withCount('versions')
            ->when($data['topic'] ?? null, fn ($q, $topic) => $q->where('topic', $topic))
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($data['source'] ?? null, fn ($q, $source) => $q->where('source_id', $source))
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 100);

        return response()->json([
            'notes' => $notes->getCollection()->map(fn (SourceNote $note) => self::present($note) + ['source' => $note->source]),
            'meta' => ['page' => $notes->currentPage(), 'last_page' => $notes->lastPage(), 'total' => $notes->total()],
        ]);
    }

    public function store(Request $request, Source $source): JsonResponse
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(SourceNoteKind::class)],
            'content' => ['required', 'string'],
        ]);

        $note = DB::transaction(function () use ($source, $data) {
            $note = $source->notes()->create(['topic' => $data['topic'], 'kind' => $data['kind']]);
            $note->setContent($data['content'], Tick::current());

            return $note;
        });

        return response()->json(['note' => self::present($note->load('latestVersion')->loadCount('versions'))], 201);
    }

    public function show(SourceNote $note): JsonResponse
    {
        return response()->json([
            'note' => self::present($note->load('latestVersion')->loadCount('versions')),
            'source' => $note->source,
            'versions' => $note->versions()->orderBy('id')->get(['id', 'tick_id', 'content', 'created_at']),
        ]);
    }

    /**
     * Onderwerp of soort wijzigen, bijv. als een TF-onderwerp wordt gesplitst of samengevoegd.
     */
    public function update(Request $request, SourceNote $note): JsonResponse
    {
        $note->update($request->validate([
            'topic' => ['sometimes', 'string', 'max:120'],
            'kind' => ['sometimes', Rule::enum(SourceNoteKind::class)],
        ]));

        return response()->json(['note' => self::present($note->load('latestVersion')->loadCount('versions'))]);
    }

    public function storeVersion(Request $request, SourceNote $note): JsonResponse
    {
        $content = $request->validate(['content' => ['required', 'string']])['content'];
        $version = $note->setContent($content, Tick::current());

        return response()->json([
            'version_id' => $version->id,
            'created' => $version->wasRecentlyCreated,
        ], $version->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(SourceNote $note): JsonResponse
    {
        $note->delete();

        return response()->json(['deleted' => $note->id]);
    }

    public static function present(SourceNote $note): array
    {
        return [
            'id' => $note->id,
            'source_id' => $note->source_id,
            'topic' => $note->topic,
            'kind' => $note->kind,
            'content' => $note->latestVersion?->content,
            'version_id' => $note->latestVersion?->id,
            'versions' => $note->versions_count,
            'created_at' => $note->created_at,
            'updated_at' => $note->latestVersion?->created_at ?? $note->updated_at,
        ];
    }
}
