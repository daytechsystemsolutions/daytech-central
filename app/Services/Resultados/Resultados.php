<?php

namespace App\Services\Resultados;

use App\Models\Marca;
use App\Models\Post;
use Illuminate\Support\Collection;

/** Resumo, destaques e lista dos posts de um período. Os números de cada post são os acumulados até a última leitura. */
class Resultados
{
    public const RESUMO = [
        'visualizacoes' => 'visualizações', 'alcance' => 'alcance',
        'salvos' => 'salvos', 'compartilhamentos' => 'compartilhados', 'novos_seguidores' => 'novos seguidores',
    ];

    public function posts(Marca $marca, Periodo $p): Collection
    {
        return $marca->posts()->with(['ultimaMetrica', 'conteudo'])
            ->whereBetween('publicado_em', [$p->inicio->startOfDay(), $p->fim->endOfDay()])->get();
    }

    public function resumo(Collection $posts, Collection $anteriores): array
    {
        $soma = fn (Collection $c, string $col) => $c->sum(fn (Post $p) => (int) ($p->ultimaMetrica?->{$col} ?? 0));
        $linhas = [['nome' => 'posts', 'valor' => $posts->count(), 'variacao' => $this->variacao($posts->count(), $anteriores->count(), false)]];
        foreach (self::RESUMO as $col => $nome) {
            $linhas[] = ['nome' => $nome, 'valor' => $soma($posts, $col), 'variacao' => $this->variacao($soma($posts, $col), $soma($anteriores, $col))];
        }

        return $linhas;
    }

    /** Os 3 de maior nota, com o motivo: a parte em que mais passou da média. */
    public function destaques(Collection $posts): Collection
    {
        return $posts->whereNotNull('nota')->sortByDesc('nota')->take(3)->values()->map(function (Post $p) {
            $partes = collect($p->nota_partes['partes'] ?? [])->sortByDesc('pontos');
            $melhor = $partes->keys()->first();
            $parte = $partes->first();
            $vezes = $parte && $parte['media'] ? round($parte['valor'] / $parte['media'], 1) : null;
            $nome = ['alcance' => 'alcance', 'valor' => 'salvos e compartilhamentos', 'crescimento' => 'seguidores e visitas ao perfil', 'interesse' => 'toques no perfil', 'retencao' => 'tempo assistido'][$melhor] ?? null;

            return ['post' => $p, 'motivo' => $nome && $vezes && $vezes > 1
                ? number_format($vezes, 1, ',', '').'x a média em '.$nome
                : ($nome ? 'melhor parte: '.$nome : null)];
        });
    }

    private function variacao(int|float $agora, int|float $antes, bool $porcento = true): ?string
    {
        if ($antes == 0) {
            return null;
        }
        if (! $porcento) {
            $d = $agora - $antes;

            return $d === 0 ? '=' : ($d > 0 ? '+'.$d : '−'.abs($d));
        }
        $pc = (int) round(($agora - $antes) * 100 / $antes);

        return $pc === 0 ? '=' : ($pc > 0 ? '+'.$pc.'%' : '−'.abs($pc).'%');
    }
}
