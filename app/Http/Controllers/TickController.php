<?php

namespace App\Http\Controllers;

use App\Enums\TickEventLabel;
use App\Enums\TickRole;
use App\Exceptions\InvalidTickTransition;
use App\Juno;
use App\Models\Tick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TickController extends Controller
{
    /**
     * Zet nu een tick klaar, los van het interval van de scheduler (voor handmatig testen vanuit de
     * kernel). Dezelfde regels als de scheduler: niet als Juno gepauzeerd is of er een tick open staat.
     */
    public function store(Request $request): JsonResponse
    {
        $role = $request->validate(['role' => ['nullable', Rule::enum(TickRole::class)]])['role'] ?? null;
        $tick = Tick::schedule($role ? TickRole::from($role) : null);

        if (! $tick) {
            return response()->json([
                'message' => Juno::isPaused() ? 'Juno is gepauzeerd.' : 'Er staat al een tick open.',
            ], 409);
        }

        return response()->json(['id' => $tick->id, 'parent_id' => $tick->parent_id, 'role' => $tick->role], 201);
    }

    public function claim(): JsonResponse|Response
    {
        $tick = Tick::claimNext();

        return $tick
            ? response()->json(['id' => $tick->id, 'parent_id' => $tick->parent_id, 'role' => $tick->role])
            : response()->noContent();
    }

    public function storeEvent(Request $request, Tick $tick): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', Rule::enum(TickEventLabel::class)->only(TickEventLabel::reportable())],
            'description' => ['nullable', 'string', 'max:65535'],
            'happened_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        try {
            $event = $tick->setStatus($data);
        } catch (InvalidTickTransition $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($event, 201);
    }
}
