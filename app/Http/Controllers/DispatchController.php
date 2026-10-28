<?php

namespace App\Http\Controllers;

use App\Enums\DispatchEventLabel;
use App\Exceptions\InvalidDispatchTransition;
use App\Models\Dispatch;
use App\Models\Tick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DispatchController extends Controller
{
    /**
     * Registreert een opdracht bij $tick en zet meteen Started. Geeft ook de time-out mee, zodat de
     * kernel die niet zelf hoeft te kennen.
     */
    public function store(Request $request, Tick $tick): JsonResponse
    {
        $data = $request->validate([
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'prompt' => ['required', 'string'],
            'permission_mode' => ['required', Rule::in(Dispatch::PERMISSION_MODES)],
        ]);

        $dispatch = DB::transaction(function () use ($tick, $data) {
            $dispatch = $tick->dispatches()->create($data);
            $dispatch->setStatus(['label' => DispatchEventLabel::Started, 'description' => 'Gestart door de kernel.']);

            return $dispatch;
        });

        return response()->json([
            'id' => $dispatch->id,
            'timeout_seconds' => (int) config('juno.dispatch_timeout') * 60,
        ], 201);
    }

    public function storeEvent(Request $request, Dispatch $dispatch): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', Rule::enum(DispatchEventLabel::class)->only(DispatchEventLabel::reportable())],
            'description' => ['nullable', 'string', 'max:65535'],
            'happened_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        try {
            $event = $dispatch->setStatus($data);
        } catch (InvalidDispatchTransition $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($event, 201);
    }

    public function storeResult(Request $request, Dispatch $dispatch): JsonResponse
    {
        if ($dispatch->result()->exists()) {
            return response()->json(['message' => "Dispatch {$dispatch->id} heeft al een resultaat."], 409);
        }

        $data = $request->validate([
            'session_id' => ['nullable', 'uuid'],
            'output' => ['nullable', 'string'],
            'exit_code' => ['nullable', 'integer'],
            'cost_usd' => ['nullable', 'numeric'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'num_turns' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(['id' => $dispatch->result()->create($data)->id], 201);
    }
}
