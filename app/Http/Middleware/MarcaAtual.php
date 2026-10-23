<?php

namespace App\Http\Middleware;

use App\Models\Marca;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Qual marca está aberta (fica na sessão) e a lista para o seletor do topo. */
class MarcaAtual
{
    public function handle(Request $request, Closure $next): Response
    {
        $marcas = Marca::ativas()->get();
        $atual = $marcas->firstWhere('slug', $request->session()->get('marca')) ?? $marcas->first();

        $request->attributes->set('marca', $atual);
        View::share(['marcas' => $marcas, 'marcaAtual' => $atual]);

        return $next($request);
    }
}
