<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\Resultados\Aprendizados;
use App\Services\Resultados\Periodo;
use App\Services\Resultados\Resultados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultadosController extends Controller
{
    public function index(Request $request, Resultados $resultados): View
    {
        $marca = $request->attributes->get('marca');
        $periodo = Periodo::de($request->query('periodo'), $request->query('data'));
        $ordem = $request->query('ordem') === 'recentes' ? 'recentes' : 'nota';

        $posts = $resultados->posts($marca, $periodo);
        $anteriores = $resultados->posts($marca, $periodo->anterior());
        $ordenados = $ordem === 'nota'
            ? $posts->sortBy([fn ($a, $b) => ($b->nota ?? -1) <=> ($a->nota ?? -1)])
            : $posts->sortByDesc('publicado_em');
        $grupo = fn (Post $p) => $periodo->tipo === 'ano'
            ? $p->publicado_em->locale('pt_BR')->translatedFormat('F')
            : 'Semana de '.$p->publicado_em->startOfWeek(\Carbon\CarbonInterface::MONDAY)->format('d/m').' a '.$p->publicado_em->endOfWeek(\Carbon\CarbonInterface::SUNDAY)->format('d/m');

        return view('resultados.index', [
            'periodo' => $periodo,
            'ordem' => $ordem,
            'resumo' => $resultados->resumo($posts, $anteriores),
            'destaques' => $resultados->destaques($posts),
            'leads' => $marca->leads()->whereBetween('recebido_em', [$periodo->inicio->startOfDay(), $periodo->fim->endOfDay()])->count(),
            // Em "melhor nota primeiro" a lista é uma só; em "mais recentes", agrupada por semana (ou mês, no ano).
            'grupos' => $ordenados->isEmpty() ? collect() : ($ordem === 'nota' ? collect(['' => $ordenados->values()]) : $ordenados->groupBy($grupo)),
        ]);
    }

    public function post(Request $request, Post $post): View
    {
        abort_unless($post->marca_id === $request->attributes->get('marca')->id, 404);
        $post->load(['metricas', 'conteudo', 'comentarios']);
        $leituras = $post->metricas;
        $em = fn (int $dias) => $leituras->first(fn ($m) => $m->dia->gte($post->publicado_em->copy()->addDays($dias)->startOfDay()));

        // Rastro até o site: cliques no link curto do post e leads que chegaram com a UTM dele (utm_content = nome).
        $rastro = $post->conteudo ? [
            'cliques' => $post->conteudo->cliques()->count(),
            'leads' => $post->marca->leads()->where('conteudo', $post->conteudo->nome)->count(),
            'link' => $post->conteudo->codigo_link ? route('link', $post->conteudo->codigo_link) : null,
            'destino' => $post->conteudo->destino ?: '/',
        ] : null;

        return view('resultados.post', [
            'post' => $post,
            'rastro' => $rastro,
            'ultima' => $leituras->last(),
            'evolucao' => [
                ['quando' => '1º dia', 'leitura' => $leituras->first()],
                ['quando' => '7 dias', 'leitura' => $em(7), 'data' => $post->publicado_em->copy()->addDays(7)],
                ['quando' => '30 dias', 'leitura' => $em(30), 'data' => $post->publicado_em->copy()->addDays(30)],
            ],
        ]);
    }

    public function aprendizados(Request $request, Aprendizados $aprendizados): View
    {
        $marca = $request->attributes->get('marca');

        return view('resultados.aprendizados', $aprendizados->daMarca($marca) + [
            'seguidores' => $marca->seguidores(),
            'regras' => $marca->regras()->where('vigente', true)->orderByRaw("case tipo when 'faca' then 0 when 'evite' then 1 else 2 end")->get(),
        ]);
    }

    /** Liga ou desliga uma regra (a produção só segue as ligadas). */
    public function alternarRegra(Request $request, \App\Models\Regra $regra): \Illuminate\Http\RedirectResponse
    {
        abort_unless($regra->marca_id === $request->attributes->get('marca')->id, 404);
        $regra->forceFill(['ativa' => ! $regra->ativa])->save();

        return back()->with('ok', $regra->ativa ? 'Regra ligada.' : 'Regra desligada: a produção não segue mais esta.');
    }

    public function capa(Request $request, Post $post): StreamedResponse
    {
        abort_unless($post->marca_id === $request->attributes->get('marca')->id && $post->capa_caminho && Storage::exists($post->capa_caminho), 404);

        return Storage::response($post->capa_caminho, null, ['Cache-Control' => 'private, max-age=604800', 'Content-Type' => 'image/jpeg']);
    }
}
