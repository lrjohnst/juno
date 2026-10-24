<?php

namespace App\Http\Controllers\Ui;

use App\Exceptions\InvalidDocumentContent;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Tick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(): View
    {
        $head = Tick::head();
        $documents = Document::withCount('versions')->orderBy('name')->get();

        return view('documents.index', [
            'head' => $head,
            'documents' => $documents,
            'current' => $head ? $documents->mapWithKeys(fn (Document $d) => [$d->id => $d->getVersion($head)]) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Wordt een bestandsnaam in workspace/context: geen paden en geen punt vooraan.
            'name' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', 'unique:documents,name'],
            'follows_rollback' => ['sometimes', 'boolean'],
        ], ['name.regex' => 'Alleen letters, cijfers, punt, streepje en underscore; geen punt vooraan.']);

        $document = Document::create([
            'name' => $data['name'],
            'follows_rollback' => $request->boolean('follows_rollback'),
        ]);

        return redirect()->route('documents.show', $document)->with('success', "Document {$document->name} aangemaakt.");
    }

    public function show(Request $request, Document $document): View
    {
        $head = Tick::head();
        $current = $head ? $document->getVersion($head) : null;
        $shown = $request->filled('version')
            ? $document->versions()->with('tick')->findOrFail($request->integer('version'))
            : $current;

        return view('documents.show', [
            'document' => $document,
            'head' => $head,
            'current' => $current,
            'shown' => $shown,
            'versions' => $document->versions()->with('tick.latestEvent')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $document->update(['follows_rollback' => $request->boolean('follows_rollback')]);

        return back()->with('success', $document->follows_rollback
            ? "{$document->name} volgt nu een rollback."
            : "{$document->name} volgt een rollback niet meer: elke tick ziet de nieuwste versie.");
    }

    /**
     * Nieuwe versie door Lucas. Hangt aan de HEAD-tick, zoals besproken: een rollback naar een eerdere
     * tick draait hem dan mee terug (als follows_rollback aan staat).
     */
    public function storeVersion(Request $request, Document $document): RedirectResponse
    {
        $content = $request->validate(['content' => ['present', 'nullable', 'string']])['content'] ?? '';
        $head = Tick::head();

        if (! $head) {
            return back()->withInput()->with('error', 'Er is nog geen tick om de versie aan te hangen.');
        }

        try {
            $version = $document->setContent($content, $head);
        } catch (InvalidDocumentContent $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('documents.show', $document)->with(
            $version->wasRecentlyCreated ? 'success' : 'info',
            $version->wasRecentlyCreated ? "Versie {$version->id} vastgelegd bij tick {$head->id}." : 'Niets veranderd: de inhoud is gelijk aan de huidige versie.',
        );
    }
}
