<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->query('q');

        return view('library.index', [
            'sources' => Source::withCount('notes')
                ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('authors', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
                ->latest('id')->paginate(30)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function show(Request $request, Source $source): View
    {
        return view('library.show', [
            'source' => $source->load('tick'),
            'notes' => $source->notes()->with(['latestVersion.tick', 'versions' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get(),
            'openNote' => $request->integer('note') ?: null,
        ]);
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('library.index')->with('success', "Bron {$source->id} met al zijn aantekeningen verwijderd.");
    }
}
