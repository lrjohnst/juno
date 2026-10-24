<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use Illuminate\View\View;

class DispatchController extends Controller
{
    public function index(): View
    {
        return view('dispatches.index', [
            'dispatches' => Dispatch::with(['latestEvent', 'result'])->latest('id')->paginate(30),
        ]);
    }

    public function show(Dispatch $dispatch): View
    {
        return view('dispatches.show', [
            'dispatch' => $dispatch->load(['events' => fn ($q) => $q->orderBy('happened_at')->orderBy('id'), 'result', 'tick.latestEvent']),
            'status' => $dispatch->getStatus(),
        ]);
    }
}
