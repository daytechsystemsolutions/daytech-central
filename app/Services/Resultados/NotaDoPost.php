<?php

namespace App\Services\Resultados;

use App\Models\Marca;
use App\Models\Post;
use App\Models\PostMetrica;
use Illuminate\Support\Collection;

/**
 * Nota do post, de 0 a 100, sempre comparada com a própria conta (plano aprovado em 23/10/2026).
 *
 *   pontos da parte = mínimo(100, 50 × valor do post ÷ mediana dos últimos 20 posts)
 *   nota = média ponderada dos pontos das partes que o post tem (vídeo não tem crescimento nem interesse;
 *          só o Reels tem retenção = tempo médio assistido, comparado com os outros Reels da conta)
 *
 * 50 = na média da conta; 100 = o dobro ou mais. Fecha no 7º dia (usa a leitura do 7º dia em diante); antes é parcial.
 */
class NotaDoPost
{
    public const PARTES = ['alcance', 'valor', 'crescimento', 'interesse', 'retencao'];

    /** Pesos por fase da conta. As quatro primeiras somam 100; a retenção só existe no Reels (a conta é refeita pelo que o post tem). */
    public const PESOS = [
        'conhecido' => ['alcance' => 30, 'valor' => 30, 'crescimento' => 25, 'interesse' => 15, 'retencao' => 25],
        'clientes' => ['alcance' => 15, 'valor' => 25, 'crescimento' => 20, 'interesse' => 40, 'retencao' => 15],
    ];

    public const DIAS_PARA_FECHAR = 7;

    public const POSTS_NA_MEDIA = 20;

    /** Médias por post durante uma rodada (na instância: não sobra de uma rodada para a outra). */
    private array $cache = [];

    /** Recalcula as notas abertas da marca e fecha as que chegaram ao 7º dia. Devolve quantas mudaram. */
    public function atualizar(Marca $marca): int
    {
        $posts = $marca->posts()->where('sem_numeros', false)->whereNull('nota_fechada_em')->with('metricas')->get();
        $pesos = self::PESOS[$marca->faseAtual()];
        $mudou = 0;

        foreach ($posts as $post) {
            $leitura = $this->leitura($post);
            if (! $leitura || ! $leitura->alcance) {
                continue;
            }
            $partes = $this->calcular($post, $leitura, $this->medias($marca, $post), $pesos);
            $fecha = $post->publicado_em->copy()->addDays(self::DIAS_PARA_FECHAR)->lte(now());
            $post->forceFill([
                'nota' => $partes['nota'],
                'nota_partes' => $partes,
                'nota_fechada_em' => $fecha ? now() : null,
            ])->save();
            $mudou++;
        }

        return $mudou;
    }

    public static function faixa(?int $nota): array
    {
        return match (true) {
            $nota === null => ['sem nota', 'neutro'],
            $nota >= 70 => ['ótimo', 'espera'],
            $nota >= 50 => ['bom', 'agendado'],
            $nota >= 30 => ['fraco', 'atencao'],
            default => ['não decolou', 'neutro'],
        };
    }

    /** Valores das partes a partir de uma leitura (null = o post não tem esse número). */
    public static function valores(PostMetrica $m): array
    {
        $alcance = max(1, (int) $m->alcance);
        $taxa = fn (?int ...$numeros) => in_array(null, $numeros, true) ? null : round(array_sum($numeros) * 100 / $alcance, 2);

        return [
            'alcance' => (int) $m->alcance,
            'valor' => $taxa($m->salvos, $m->compartilhamentos),
            'crescimento' => $taxa($m->novos_seguidores, $m->visitas_perfil),
            'interesse' => $taxa($m->acoes_perfil),
            'retencao' => $m->tempo_medio_ms !== null ? round($m->tempo_medio_ms / 1000, 1) : null, // segundos
        ];
    }

    /** Leitura que conta: a primeira do 7º dia em diante; antes disso, a mais recente. */
    private function leitura(Post $post): ?PostMetrica
    {
        $limite = $post->publicado_em->copy()->addDays(self::DIAS_PARA_FECHAR)->startOfDay();

        return $post->metricas->first(fn (PostMetrica $m) => $m->dia->gte($limite)) ?? $post->metricas->last();
    }

    /** Mediana de cada parte nos últimos 20 posts da marca com números (sem contar o próprio post). */
    private function medias(Marca $marca, Post $post): array
    {
        $chave = $marca->id.':'.$post->id;
        if (isset($this->cache[$chave])) {
            return $this->cache[$chave];
        }

        $outros = $marca->posts()->where('sem_numeros', false)->where('id', '!=', $post->id)
            ->latest('publicado_em')->with('ultimaMetrica')->limit(self::POSTS_NA_MEDIA)->get()
            ->map(fn (Post $p) => $p->ultimaMetrica ? self::valores($p->ultimaMetrica) : null)->filter();

        $medias = [];
        foreach (self::PARTES as $parte) {
            $medias[$parte] = $this->mediana($outros->pluck($parte)->filter(fn ($v) => $v !== null)->values());
        }

        return $this->cache[$chave] = $medias;
    }

    private function mediana(Collection $valores): ?float
    {
        if ($valores->isEmpty()) {
            return null;
        }
        $ordenados = $valores->sort()->values();
        $meio = intdiv($ordenados->count(), 2);

        return $ordenados->count() % 2 ? (float) $ordenados[$meio] : ($ordenados[$meio - 1] + $ordenados[$meio]) / 2;
    }

    private function calcular(Post $post, PostMetrica $leitura, array $medias, array $pesos): array
    {
        $valores = self::valores($leitura);
        $partes = [];
        $soma = 0;
        $somaPesos = 0;

        foreach (self::PARTES as $parte) {
            $valor = $valores[$parte];
            if ($valor === null) {
                continue; // o post não tem esse número (ex.: vídeo): a parte sai da conta
            }
            $media = $medias[$parte];
            $pontos = match (true) {
                $media === null => 50,                   // sem histórico: conta como na média
                $media == 0 => $valor > 0 ? 100 : 50,    // a conta costuma ter zero: qualquer resultado é destaque
                default => (int) round(min(100, 50 * $valor / $media)),
            };
            $partes[$parte] = ['valor' => $valor, 'media' => $media, 'pontos' => $pontos, 'peso' => $pesos[$parte]];
            $soma += $pontos * $pesos[$parte];
            $somaPesos += $pesos[$parte];
        }

        return [
            'nota' => $somaPesos ? (int) round($soma / $somaPesos) : null,
            'partes' => $partes,
            'leitura_dia' => $leitura->dia->toDateString(),
        ];
    }
}
