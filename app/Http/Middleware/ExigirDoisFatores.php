<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 2 fatores é obrigatório: sem ele confirmado, a única tela liberada é a de Segurança. */
class ExigirDoisFatores
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->two_factor_confirmed_at === null && ! $request->routeIs('seguranca')) {
            return redirect()->route('seguranca');
        }

        return $next($request);
    }
}
