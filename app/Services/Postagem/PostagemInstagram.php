<?php

namespace App\Services\Postagem;

use App\Models\Conteudo;
use App\Models\Marca;
use App\Models\Post;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramErro;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Posta no Instagram, pela VPS, o carrossel aprovado cujo horário chegou. Só posta quando TUDO vale:
 * - a marca está com "Postar sozinho no horário" ligado (o usuário liga em Marcas);
 * - o carrossel está "agendado" com a decisão "aprovar" DESTA versão (o "Aprovar e agendar" do usuário);
 * - as lâminas guardadas são as mesmas que o usuário viu (impressão digital);
 * - o horário já passou e ele ainda não foi publicado.
 * As imagens vão por links da própria Central que expiram em 2 horas (o Instagram baixa por eles).
 */
class PostagemInstagram
{
    public function __construct(private readonly InstagramClient $api) {}

    /** Próximo carrossel pronto para postar da marca (ou null). */
    public function proximo(Marca $marca): ?Conteudo
    {
        if (! $marca->postagem_automatica || ! $marca->instagramConectado()) {
            return null;
        }

        return $marca->conteudos()->where('estado', 'agendado')->where('decisao', 'aprovar')
            ->whereColumn('versao_decidida', 'versao')->where('agendado_para', '<=', now())
            ->where('agendado_para', '>=', $this->inicioDaJanela($marca))
            ->whereNull('publicado_em')->orderBy('agendado_para')->first();
    }

    /**
     * Só posta o que venceu depois que a postagem foi ligada e há no máximo 6 horas: aprovado antigo com horário
     * perdido não sai de surpresa (vira "falhou" e o usuário decide com "Tentar de novo").
     */
    public function inicioDaJanela(Marca $marca): CarbonImmutable
    {
        $ligada = CarbonImmutable::parse($marca->postagem_ligada_em ?? now())->subMinutes(10);
        $seisHoras = CarbonImmutable::now()->subHours(6);

        return $ligada->gt($seisHoras) ? $ligada : $seisHoras;
    }

    /** Aprovados com horário perdido (antes da janela): marca como falhou, com o motivo. */
    public function marcarPerdidos(Marca $marca): int
    {
        if (! $marca->postagem_automatica) {
            return 0;
        }

        return $marca->conteudos()->where('estado', 'agendado')->whereNull('publicado_em')
            ->where('agendado_para', '<', $this->inicioDaJanela($marca))
            ->update(['estado' => 'falhou', 'erro_postagem' => 'O horário passou sem a postagem estar ligada. Toque em "Tentar de novo" para postar agora.']);
    }

    /**
     * Reserva o carrossel (agendado → postando, update condicional: duas rodadas nunca pegam o mesmo) e posta.
     * Depois do media_publish, o post está no ar: daí em diante nada vira "falhou".
     *
     * @throws InstagramErro antes de publicar (o carrossel fica "falhou" e nada saiu no Instagram)
     */
    public function postar(Conteudo $conteudo): ?Conteudo
    {
        $reservado = Conteudo::whereKey($conteudo->id)->where('estado', 'agendado')->where('decisao', 'aprovar')
            ->whereColumn('versao_decidida', 'versao')->update(['estado' => 'postando']);
        if (! $reservado) {
            return null; // mudou no meio (desfeito, tirado, outra rodada): não posta
        }
        $conteudo->refresh();

        try {
            $publicado = $this->publicar($conteudo);
        } catch (\Throwable $e) {
            Conteudo::whereKey($conteudo->id)->where('estado', 'postando')->update(['estado' => 'falhou',
                'erro_postagem' => $e instanceof InstagramErro ? $e->getMessage() : 'Erro inesperado antes de publicar. Tente de novo.']);
            throw $e instanceof InstagramErro ? $e : new InstagramErro('Erro inesperado antes de publicar.');
        }

        // No ar: grava já (se algo falhar daqui pra frente, o post não pode parecer "não publicado").
        $conteudo->forceFill(['estado' => 'publicado', 'publicado_em' => now(), 'erro_postagem' => null])->save();
        $post = Post::firstOrCreate(['ig_id' => $publicado], [
            'marca_id' => $conteudo->marca_id, 'conteudo_id' => $conteudo->id,
            'tipo_midia' => $conteudo->laminasAtuais()->count() === 1 ? 'IMAGE' : 'CAROUSEL_ALBUM',
            'legenda' => $conteudo->legenda_instagram, 'publicado_em' => now(),
        ]);

        try {
            $dados = $this->api->get($conteudo->marca->instagram_token, $publicado, ['fields' => 'permalink,timestamp']);
            $quando = isset($dados['timestamp']) ? CarbonImmutable::parse($dados['timestamp'])->setTimezone(config('app.timezone')) : now();
            $conteudo->forceFill(['link' => $dados['permalink'] ?? null, 'publicado_em' => $quando])->save();
            $post->forceFill(['permalink' => $dados['permalink'] ?? null, 'publicado_em' => $quando])->save();
        } catch (\Throwable) {
            // Sem o link agora: a coleta das 6h15 traz.
        }

        return $conteudo->fresh();
    }

    /** Cria os contêineres e publica. Devolve o id do post no Instagram. */
    private function publicar(Conteudo $conteudo): string
    {
        $marca = $conteudo->marca;
        $laminas = $conteudo->laminasAtuais();
        $imagens = $laminas->map(fn ($l) => Storage::get($l->caminho))->all();
        if ($laminas->isEmpty() || $laminas->count() > 10) {
            throw new InstagramErro('O carrossel precisa ter de 1 a 10 lâminas para o Instagram.');
        }
        if (! $conteudo->hash_laminas || Conteudo::hashDasLaminas($imagens) !== $conteudo->hash_laminas) {
            throw new InstagramErro('As lâminas guardadas não batem com as que você aprovou. Peça um ajuste.');
        }

        $token = $marca->instagram_token;
        $conta = $marca->instagram_conta_id;
        $url = fn ($l) => URL::temporarySignedRoute('publico.lamina', now()->addHours(2), ['conteudo' => $conteudo->id, 'lamina' => $l->id]);
        $legenda = (string) $conteudo->legenda_instagram;
        if (mb_strlen($legenda) > 2200 || preg_match_all('/#[\p{L}\p{N}_]+/u', $legenda) > 30) {
            throw new InstagramErro('A legenda passa do limite do Instagram (2.200 letras ou 30 hashtags). Peça um ajuste.');
        }

        if ($laminas->count() === 1) {
            $principal = $this->api->post($token, "{$conta}/media", ['image_url' => $url($laminas->first()), 'caption' => $legenda])['id'];
        } else {
            $filhos = [];
            foreach ($laminas as $l) {
                $filhos[] = $this->api->post($token, "{$conta}/media", ['image_url' => $url($l), 'is_carousel_item' => 'true'])['id'];
            }
            foreach ($filhos as $filho) {
                $this->esperarPronto($token, $filho);
            }
            $principal = $this->api->post($token, "{$conta}/media", ['media_type' => 'CAROUSEL', 'children' => implode(',', $filhos), 'caption' => $legenda])['id'];
        }
        $this->esperarPronto($token, $principal);

        return (string) $this->api->post($token, "{$conta}/media_publish", ['creation_id' => $principal])['id'];
    }

    /** O Instagram processa a imagem antes de publicar: espera "FINISHED" (até ~1 min). */
    private function esperarPronto(string $token, string $contenedor): void
    {
        for ($i = 0; $i < 12; $i++) {
            $estado = $this->api->get($token, $contenedor, ['fields' => 'status_code'])['status_code'] ?? 'IN_PROGRESS';
            if ($estado === 'FINISHED') {
                return;
            }
            if (in_array($estado, ['ERROR', 'EXPIRED'], true)) {
                throw new InstagramErro("O Instagram recusou uma imagem ({$estado}).");
            }
            sleep(app()->runningUnitTests() ? 0 : 5);
        }
        throw new InstagramErro('O Instagram demorou demais para processar as imagens.');
    }
}
