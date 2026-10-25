<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

class TokenController extends Controller
{
    public function index(): View
    {
        return view('tokens.index', [
            'tokens' => ApiToken::with(['abilities', 'events' => fn ($q) => $q->orderBy('happened_at')->orderBy('id')])->orderBy('id')->get(),
            'abilities' => ApiToken::ABILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:api_tokens,name'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(ApiToken::ABILITIES))],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        [$token, $plain] = ApiToken::issue($data['name'], $data['abilities'], $data['description'] ?? null);

        // Eén keer tonen; daarna staat alleen de hash nog in de database.
        return redirect()->route('tokens.index')->with('newToken', ['name' => $token->name, 'plain' => $plain]);
    }

    public function update(Request $request, ApiToken $token): RedirectResponse
    {
        $abilities = $request->validate([
            'abilities' => ['nullable', 'array'],
            'abilities.*' => [Rule::in(array_keys(ApiToken::ABILITIES))],
        ])['abilities'] ?? [];

        $token->setAbilities($abilities);

        return back()->with('success', "Rechten van {$token->name} bijgewerkt.");
    }

    public function revoke(Request $request, ApiToken $token): RedirectResponse
    {
        $description = $request->validate(['description' => ['required', 'string', 'max:500']])['description'];

        try {
            $token->revoke($description);
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Token {$token->name} ingetrokken.");
    }
}
