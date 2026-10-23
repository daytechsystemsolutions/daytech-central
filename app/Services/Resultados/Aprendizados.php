<?php

namespace App\Services\Resultados;

use App\Models\Marca;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * "O que aprendemos": nota média por formato, dia, horário e, quando a IA classificar os posts, por pilar, público e
 * chamada final. Cada linha mostra quantos posts a sustentam (com poucos, é só pista).
 */
class Aprendizados
{
    public const POSTS_PARA_VALER = 10;

    public function daMarca(Marca $marca): array
    {
        $posts = $marca->posts()->whereNotNull('nota')->where('publicado_em', '>=', now()->subYear())->get();

        $formato = ['CAROUSEL_ALBUM' => 'Carrossel', 'VIDEO' => 'Vídeo', 'IMAGE' => 'Imagem'];
        $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
        $horario = fn (Post $p) => match (true) {
            $p->publicado_em->hour >= 5 && $p->publicado_em->hour < 12 => 'Manhã (5h às 12h)',
            $p->publicado_em->hour >= 12 && $p->publicado_em->hour < 18 => 'Tarde (12h às 18h)',
            default => 'Noite (18h às 5h)',
        };
        $atributo = fn (string $campo) => fn (Post $p) => isset($p->como_foi_feito[$campo]) ? Rotulos::de($p->como_foi_feito[$campo]) : null;

        return [
            'total' => $posts->count(),
            'vale' => $posts->count() >= self::POSTS_PARA_VALER,
            'blocos' => array_values(array_filter([
                $this->bloco('Por formato', $posts, fn (Post $p) => $formato[$p->tipo_midia] ?? $p->tipo_midia),
                $this->bloco('Por dia da semana', $posts, fn (Post $p) => ucfirst($dias[$p->publicado_em->dayOfWeek])),
                $this->bloco('Por horário', $posts, $horario),
                $this->bloco('Por pilar', $posts, $atributo('pilar')),
                $this->bloco('Por público', $posts, $atributo('publico_principal')),
                $this->bloco('Por chamada final', $posts, $atributo('chamada')),
                $this->bloco('Por formato do conteúdo', $posts, $atributo('formato')),
            ])),
            'sem_classificacao' => $posts->every(fn (Post $p) => empty($p->como_foi_feito)),
        ];
    }

    /** @return array{titulo: string, linhas: list<array>}|null */
    private function bloco(string $titulo, Collection $posts, callable $grupo): ?array
    {
        $linhas = $posts->groupBy(fn (Post $p) => $grupo($p) ?? '')->forget('')
            ->map(fn (Collection $c, $nome) => ['nome' => $nome, 'nota' => (int) round($c->avg('nota')), 'posts' => $c->count()])
            ->sortByDesc('nota')->values();

        return $linhas->isEmpty() ? null : ['titulo' => $titulo, 'linhas' => $linhas->all()];
    }
}
