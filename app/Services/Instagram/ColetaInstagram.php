<?php

namespace App\Services\Instagram;

use App\Models\Conteudo;
use App\Models\ContaDia;
use App\Models\Marca;
use App\Models\Post;
use App\Models\PostComentario;
use App\Models\PostMetrica;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Busca no Instagram os números da conta, os posts do perfil, os números de cada post e os comentários.
 * Só leitura: nada é postado nem respondido daqui.
 */
class ColetaInstagram
{
    public const METRICAS_POST = ['views', 'reach', 'likes', 'comments', 'saved', 'shares', 'total_interactions',
        'follows', 'profile_visits', 'profile_activity'];

    /** Vídeo (Reels) não tem novos seguidores, visitas e ações no perfil por post. */
    public const METRICAS_VIDEO = ['views', 'reach', 'likes', 'comments', 'saved', 'shares', 'total_interactions'];

    public const METRICAS_CONTA = ['reach', 'profile_views', 'accounts_engaged', 'total_interactions', 'profile_links_taps'];

    public function __construct(private readonly InstagramClient $api) {}

    /** Seguidores e posts de agora + o que aconteceu no dia (padrão: ontem, porque a coleta roda de madrugada). */
    public function conta(Marca $marca, ?CarbonImmutable $dia = null): ContaDia
    {
        $dia ??= CarbonImmutable::yesterday();
        $token = $this->token($marca);
        $perfil = $this->api->get($token, $marca->instagram_conta_id, ['fields' => 'followers_count,media_count']);

        $numeros = [];
        foreach (self::METRICAS_CONTA as $metrica) {
            try {
                $r = $this->api->get($token, "{$marca->instagram_conta_id}/insights", [
                    'metric' => $metrica, 'period' => 'day', 'metric_type' => 'total_value',
                    'since' => $dia->startOfDay()->timestamp, 'until' => $dia->addDay()->startOfDay()->timestamp,
                ]);
                $numeros[$metrica] = (int) ($r['data'][0]['total_value']['value'] ?? 0);
            } catch (InstagramErro $e) {
                Log::info("coleta conta {$marca->slug}: {$metrica} indisponível", ['erro' => $e->getMessage()]);
            }
        }

        // Busca pelo dia com whereDate: o SQLite guarda a data com hora (00:00:00).
        $registro = ContaDia::where('marca_id', $marca->id)->whereDate('dia', $dia->toDateString())->first()
            ?? new ContaDia(['marca_id' => $marca->id, 'dia' => $dia->toDateString()]);
        $registro->fill([
            'seguidores' => $perfil['followers_count'] ?? null,
            'posts' => $perfil['media_count'] ?? null,
            'alcance' => $numeros['reach'] ?? null,
            'visitas_perfil' => $numeros['profile_views'] ?? null,
            'contas_engajadas' => $numeros['accounts_engaged'] ?? null,
            'interacoes' => $numeros['total_interactions'] ?? null,
            'toques_link' => $numeros['profile_links_taps'] ?? null,
        ])->save();

        return $registro;
    }

    /** Traz os posts do perfil (novos entram; os que existem atualizam legenda e link). Devolve quantos são novos. */
    public function posts(Marca $marca): int
    {
        $token = $this->token($marca);
        $midias = $this->api->todos($token, "{$marca->instagram_conta_id}/media", [
            'fields' => 'id,media_type,caption,permalink,timestamp,media_url,thumbnail_url', 'limit' => 50,
        ]);

        $novos = 0;
        foreach ($midias as $m) {
            if (empty($m['id']) || empty($m['timestamp'])) {
                continue;
            }
            $post = Post::firstOrNew(['ig_id' => $m['id']]);
            $novos += $post->exists ? 0 : 1;
            $post->fill([
                'marca_id' => $marca->id,
                'tipo_midia' => $m['media_type'] ?? 'IMAGE',
                'legenda' => $m['caption'] ?? null,
                'permalink' => $m['permalink'] ?? null,
                'publicado_em' => CarbonImmutable::parse($m['timestamp'])->setTimezone(config('app.timezone')),
            ]);
            // Feito pela Central: liga ao carrossel pelo link do post.
            if (! $post->conteudo_id && $post->permalink) {
                $post->conteudo_id = Conteudo::where('marca_id', $marca->id)->where('link', 'like', rtrim($post->permalink, '/').'%')->value('id');
            }
            $post->save();

            if (! $post->capa_caminho && ($url = $m['thumbnail_url'] ?? $m['media_url'] ?? null)) {
                $bytes = $this->api->baixar($url);
                if ($bytes && str_starts_with($bytes, "\xFF\xD8\xFF")) {
                    $caminho = "posts/{$marca->slug}/{$post->ig_id}.jpg";
                    Storage::put($caminho, $bytes);
                    $post->forceFill(['capa_caminho' => $caminho])->save();
                }
            }
        }

        return $novos;
    }

    /** Números de cada post, gravados como a leitura de hoje. Devolve quantos posts foram lidos. */
    public function metricas(Marca $marca, iterable $posts): int
    {
        $token = $this->token($marca);
        $hoje = CarbonImmutable::today()->toDateString();
        $lidos = 0;
        foreach ($posts as $post) {
            if ($post->sem_numeros) {
                continue;
            }
            $valores = $this->api->insightsDoPost($token, $post->ig_id, $post->tipo_midia === 'VIDEO' ? self::METRICAS_VIDEO : self::METRICAS_POST);
            if ($valores === []) {
                // Post antigo (de antes da conta comercial): o Instagram não tem os números. Não pede de novo.
                if ($post->publicado_em->lt(now()->subDays(7))) {
                    $post->forceFill(['sem_numeros' => true])->save();
                }

                continue;
            }
            $colunas = [];
            foreach (PostMetrica::DA_API as $nome => $coluna) {
                $colunas[$coluna] = $valores[$nome] ?? null;
            }
            $leitura = PostMetrica::where('post_id', $post->id)->whereDate('dia', $hoje)->first()
                ?? new PostMetrica(['post_id' => $post->id, 'dia' => $hoje]);
            $leitura->fill($colunas)->save();
            $post->forceFill(['metricas_em' => now()])->save();
            $lidos++;
        }

        return $lidos;
    }

    /** Comentários novos dos posts. Devolve quantos entraram. */
    public function comentarios(Marca $marca, iterable $posts): int
    {
        $token = $this->token($marca);
        $novos = 0;
        foreach ($posts as $post) {
            try {
                $lista = $this->api->todos($token, "{$post->ig_id}/comments", ['fields' => 'id,text,username,timestamp,like_count', 'limit' => 50], 5);
            } catch (InstagramErro $e) {
                if (! $e->naoSuportado()) {
                    throw $e;
                }

                continue;
            }
            foreach ($lista as $c) {
                $comentario = PostComentario::firstOrNew(['ig_id' => $c['id']]);
                $novos += $comentario->exists ? 0 : 1;
                $comentario->fill(['post_id' => $post->id, 'usuario' => $c['username'] ?? null, 'texto' => (string) ($c['text'] ?? ''),
                    'curtidas' => (int) ($c['like_count'] ?? 0), 'comentado_em' => CarbonImmutable::parse($c['timestamp'])->setTimezone(config('app.timezone'))])->save();
            }
        }

        return $novos;
    }

    private function token(Marca $marca): string
    {
        if (! $marca->instagramConectado()) {
            throw new InstagramErro("{$marca->nome} não tem Instagram conectado.");
        }

        return $marca->instagram_token;
    }
}
