<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function index(): View
    {
        $entries = JournalEntry::with('tick.latestEvent')->latest('tick_id')->latest('id')->paginate(50);

        return view('journal.index', [
            'window' => JournalEntry::window()->load('tick.latestEvent')->groupBy('tick_id'),
            'journalWindow' => (int) config('juno.journal_window'),
            'entries' => $entries,
            'grouped' => $entries->getCollection()->groupBy('tick_id'),
        ]);
    }
}
