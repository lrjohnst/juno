<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidDocumentContent;
use App\Models\Document;
use App\Models\Tick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * De stand van de gevraagde documenten bij $tick (zonder names: alle documenten).
     * Een document zonder versie op die route geeft null.
     */
    public function index(Request $request, Tick $tick): JsonResponse
    {
        $names = $request->validate([
            'names' => ['sometimes', 'array'],
            'names.*' => ['string'],
        ])['names'] ?? null;

        $documents = $names === null ? Document::all() : Document::whereIn('name', $names)->get();

        if ($names !== null && $unknown = array_diff($names, $documents->pluck('name')->all())) {
            return response()->json(['message' => 'Onbekend document: '.implode(', ', $unknown).'.'], 404);
        }

        return response()->json([
            'documents' => $documents->mapWithKeys(function (Document $document) use ($tick) {
                $version = $document->getVersion($tick);

                return [$document->name => $version ? [
                    'content' => $version->content,
                    'version_id' => $version->id,
                    'tick_id' => $version->tick_id,
                ] : null];
            }),
        ]);
    }

    public function store(Request $request, Tick $tick, string $name): JsonResponse
    {
        $document = Document::where('name', $name)->first();

        if (! $document) {
            return response()->json(['message' => "Onbekend document: {$name}."], 404);
        }

        $data = $request->validate(['content' => ['present', 'nullable', 'string']]);

        try {
            $version = $document->setContent($data['content'] ?? '', $tick);
        } catch (InvalidDocumentContent $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'version_id' => $version->id,
            'tick_id' => $version->tick_id,
            'created' => $version->wasRecentlyCreated,
        ], $version->wasRecentlyCreated ? 201 : 200);
    }
}
