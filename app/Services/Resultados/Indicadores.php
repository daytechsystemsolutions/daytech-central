<?php

namespace App\Services\Resultados;

use App\Models\Marca;
use Carbon\CarbonImmutable;

/** Números do topo do Início: seguidores, posts, nota do mês e os últimos 7 dias da conta. */
class Indicadores
{
    public function daMarca(Marca $marca): ?array
    {
        $dias = $marca->contaDias()->orderByDesc('dia')->get();
        $ultimo = $dias->first();
        if (! $ultimo) {
            return null; // ainda não houve coleta
        }

        $hoje = CarbonImmutable::today();
        $semanaPassada = $dias->first(fn ($d) => $d->dia->lte($hoje->subDays(8)) && $d->seguidores !== null);
        $inicioMes = $hoje->startOfMonth();
        $mesPassado = $inicioMes->subMonth();

        $notaMes = $this->notaMedia($marca, $inicioMes, $hoje->endOfDay());
        $notaAnterior = $this->notaMedia($marca, $mesPassado, $inicioMes->subSecond());

        $seteDias = $dias->filter(fn ($d) => $d->dia->gte($hoje->subDays(7)));
        $desde = $seteDias->min('dia');

        return [
            'seguidores' => $ultimo->seguidores,
            'seguidores_variacao' => $semanaPassada ? $ultimo->seguidores - $semanaPassada->seguidores : null,
            'posts' => $ultimo->posts,
            'posts_no_mes' => $marca->posts()->where('publicado_em', '>=', $inicioMes)->count(),
            'mes' => $hoje->locale('pt_BR')->translatedFormat('F'),
            'nota_mes' => $notaMes,
            'nota_faixa' => NotaDoPost::faixa($notaMes),
            'nota_variacao' => $notaMes !== null && $notaAnterior !== null ? $notaMes - $notaAnterior : null,
            'sete_dias' => [
                'alcance' => $seteDias->sum('alcance'),
                'interacoes' => $seteDias->sum('interacoes'),
                'visitas' => $seteDias->sum('visitas_perfil'),
                'toques' => $seteDias->sum('toques_link'),
                'desde' => $desde && $desde->gt($hoje->subDays(7)) ? $desde->format('d/m') : null,
            ],
            'atualizado' => $ultimo->updated_at,
        ];
    }

    private function notaMedia(Marca $marca, CarbonImmutable $de, CarbonImmutable $ate): ?int
    {
        $media = $marca->posts()->whereBetween('publicado_em', [$de, $ate])->whereNotNull('nota')->avg('nota');

        return $media === null ? null : (int) round($media);
    }
}
