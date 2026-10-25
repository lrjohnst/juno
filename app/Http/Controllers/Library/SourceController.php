<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\SourceNote;
use App\Models\Tick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De index van de library, voor Juno en de kernel. Het tick-id vult de server zelf in: de tick die loopt.
 */
class SourceController extends Controller
{
    /**
     * ?fields=id,title,authors,year (alleen die velden), ?q= (zoekt in titel, auteurs, beschrijving),
     * ?per_page= (standaard 100, hoogstens 500).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $fields = isset($data['fields']) ? array_values(array_intersect(explode(',', $data['fields']), Source::FIELDS)) : Source::FIELDS;
        $fields = $fields ?: Source::FIELDS;

        $sources = Source::query()
            ->when($data['q'] ?? null, fn ($query, $q) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")->orWhere('authors', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 100);

        return response()->json([
            'sources' => $sources->getCollection()->map(fn (Source $source) => $source->only($fields)),
            'meta' => ['page' => $sources->currentPage(), 'last_page' => $sources->lastPage(), 'total' => $sources->total()],
        ]);
    }

    /**
     * Deterministische dubbelcheck op DOI, ISBN of URL (genormaliseerd).
     */
    public function lookup(Request $request): JsonResponse
    {
        $source = Source::findDuplicate($request->query('doi'), $request->query('isbn'), $request->query('url'));

        return $source
            ? response()->json(['source' => $source])
            : response()->json(['message' => 'Geen bron met deze DOI, ISBN of URL.'], 404);
    }

    public function show(Source $source): JsonResponse
    {
        return response()->json([
            'source' => $source,
            'notes' => $source->notes()->with('latestVersion')->withCount('versions')->orderBy('id')->get()->map(fn (SourceNote $note) => NoteController::present($note)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(required: true));

        if ($existing = Source::findDuplicate($data['doi'] ?? null, $data['isbn'] ?? null, $data['url'] ?? null)) {
            return response()->json([
                'message' => "Deze bron bestaat al (id {$existing->id}). Voeg een aantekening toe in plaats van een nieuwe bron.",
                'source_id' => $existing->id,
            ], 409);
        }

        $source = Source::create($data + ['tick_id' => Tick::current()?->id]);

        return response()->json(['source' => $source], 201);
    }

    public function update(Request $request, Source $source): JsonResponse
    {
        $data = $request->validate($this->rules(required: false));

        $doi = array_key_exists('doi', $data) ? $data['doi'] : $source->doi;
        $isbn = array_key_exists('isbn', $data) ? $data['isbn'] : $source->isbn;
        $url = array_key_exists('url', $data) ? $data['url'] : $source->url;
        if ($existing = Source::findDuplicate($doi, $isbn, $url, except: $source->id)) {
            return response()->json(['message' => "Een andere bron (id {$existing->id}) heeft al deze DOI, ISBN of URL.", 'source_id' => $existing->id], 409);
        }

        $source->update($data);

        return response()->json(['source' => $source->fresh()]);
    }

    public function destroy(Source $source): JsonResponse
    {
        $source->delete(); // aantekeningen en hun versies gaan mee (cascade)

        return response()->json(['deleted' => $source->id]);
    }

    private function rules(bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'authors' => [$req, 'array'],
            'authors.*' => ['string', 'max:200'],
            'title' => [$req, 'string', 'max:500'],
            'year' => ['nullable', 'integer', 'min:0', 'max:2100'],
            'doi' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'url', 'max:1000'],
            'description' => [$req, 'string', 'max:5000'],
        ];
    }
}
