<?php

namespace App\Http\Controllers;

use App\Models\Conteudo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Link curto de um post (central.daytechsolutions.com.br/l/{codigo}): conta o clique e leva à página do site do
 * assunto do post, com a UTM dele (o site guarda a UTM e, se virar lead, avisa a Central). Não guarda IP nem nada
 * da pessoa: só a hora e o meio (bio, story, reels).
 */
class LinkController extends Controller
{
    public const MEIOS = ['bio', 'story', 'reels', 'direct', 'legenda'];

    public function ir(Request $request, string $codigo): RedirectResponse
    {
        $conteudo = Conteudo::where('codigo_link', $codigo)->first();
        if (! $conteudo) {
            return redirect()->away(rtrim((string) config('central.site_url'), '/').'/');
        }
        $meio = in_array($request->query('m'), self::MEIOS, true) ? $request->query('m') : 'bio';
        $conteudo->cliques()->create(['meio' => $meio, 'clicado_em' => now()]);

        return redirect()->away($conteudo->urlDoDestino($meio), 302, ['Cache-Control' => 'no-store']);
    }
}
