<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gebruik: ->middleware('juno.ability:library.read'). Een geldig token zonder dat recht krijgt 403.
 */
class RequireAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = ApiToken::findActive($request->bearerToken());

        if (! $token) {
            abort(401, 'Ongeldig of ontbrekend token.');
        }

        if (! $token->can($ability)) {
            abort(403, "Dit token mag niet: {$ability}.");
        }

        $request->attributes->set('apiToken', $token);

        return $next($request);
    }
}
