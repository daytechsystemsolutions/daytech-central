<?php

namespace App\Http\Middleware;

use App\Models\TokenFabrica;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API da fábrica: só com a chave criada por `central:token-fabrica` (Authorization: Bearer). */
class AutenticarFabrica
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $registro = strlen($token) >= 40 ? TokenFabrica::where('hash', TokenFabrica::hashDe($token))->first() : null;

        if (! $registro) {
            return response()->json(['erro' => 'Chave da fábrica inválida.'], 401);
        }

        if (! $registro->ultimo_uso_em || $registro->ultimo_uso_em->lt(now()->subMinutes(5))) {
            $registro->forceFill(['ultimo_uso_em' => now()])->save();
        }

        return $next($request);
    }
}
