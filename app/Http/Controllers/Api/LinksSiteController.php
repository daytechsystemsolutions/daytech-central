<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conteudo;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;

/** A página de links da bio do site (mednextsst.com/links) pede os posts recentes e o link curto de cada um. */
class LinksSiteController extends Controller
{
    public function index(Marca $marca): JsonResponse
    {
        $posts = $marca->conteudos()->where('estado', 'publicado')->whereNotNull('publicado_em')
            ->latest('publicado_em')->limit(12)->get()
            ->map(fn (Conteudo $c) => [
                'titulo' => $c->titulo,
                'tipo' => $c->ehReels() ? 'reels' : 'carrossel',
                'publicado_em' => $c->publicado_em->toIso8601String(),
                'destino' => $c->destino ?: '/',
                'link' => route('link', $c->garantirCodigoLink()),
            ])->values();

        return response()->json(['posts' => $posts]);
    }
}
