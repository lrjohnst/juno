<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJunoToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = config('juno.api_token_hash');
        $token = $request->bearerToken();

        if (! $hash || ! $token || ! hash_equals($hash, hash('sha256', $token))) {
            abort(401, 'Ongeldig of ontbrekend token.');
        }

        return $next($request);
    }
}
