<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\SourceNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->query('q');
        $topic = $request->query('topic');

        return view('library.index', [
            'sources' => Source::withCount('notes')
                ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('authors', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
                ->when($topic, fn ($query) => $query->whereHas('notes', fn ($n) => $n->where('topic', $topic)))
                ->latest('id')->paginate(30)->withQueryString(),
            'topics' => SourceNote::query()->selectRaw('topic, count(*) as aantal')->groupBy('topic')->orderBy('topic')->pluck('aantal', 'topic'),
            'q' => $q,
            'topic' => $topic,
        ]);
    }

    public function show(Request $request, Source $source): View
    {
        return view('library.show', [
            'source' => $source->load('tick'),
            'notes' => $source->notes()->with(['latestVersion.tick', 'versions' => fn ($q) => $q->orderBy('id')])->orderBy('topic')->get(),
            'openNote' => $request->integer('note') ?: null,
        ]);
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('library.index')->with('success', "Bron {$source->id} met al zijn aantekeningen verwijderd.");
    }
}
