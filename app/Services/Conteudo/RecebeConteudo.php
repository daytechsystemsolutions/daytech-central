<?php

namespace App\Services\Conteudo;

use App\Models\CalendarioItem;
use App\Models\Conteudo;
use App\Models\Marca;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Carrossel que chega da fábrica. Versão nova = lâminas novas e volta para "espera você" (aprovação de uma versão
 * não vale para outra). Mesma versão = só atualiza os textos e mantém a decisão.
 */
class RecebeConteudo
{
    public const MAX_LAMINAS = 20;

    public const MAX_BYTES_IMAGEM = 8 * 1024 * 1024;

    public const MAX_BYTES_PDF = 25 * 1024 * 1024;

    /** O Instagram aceita Reels de até 300 MB; o limite aqui é o do envio pela Central (100 MB no nginx). */
    public const MAX_BYTES_VIDEO = 95 * 1024 * 1024;

    public function receber(Marca $marca, array $dados): Conteudo
    {
        $imagens = $this->decodificarLaminas($dados['laminas'] ?? []);
        $pdf = isset($dados['pdf']) ? $this->decodificar($dados['pdf'], '%PDF', self::MAX_BYTES_PDF, 'pdf') : null;

        return DB::transaction(function () use ($marca, $dados, $imagens, $pdf) {
            $conteudo = Conteudo::where('marca_id', $marca->id)->where('nome', $dados['nome'])->lockForUpdate()->first()
                ?? new Conteudo(['marca_id' => $marca->id, 'nome' => $dados['nome']]);
            $versaoNova = ! $conteudo->exists || (int) $dados['versao'] > $conteudo->versao;
            $tipo = $dados['tipo'] ?? 'carrossel';
            if ($conteudo->exists && $conteudo->tipo !== $tipo) {
                throw new ConflictHttpException("{$conteudo->nome} já existe como {$conteudo->tipo}.");
            }

            if ($conteudo->estado === 'postando') {
                throw new ConflictHttpException("{$conteudo->nome} está sendo postado agora. Tente de novo em alguns minutos.");
            }
            if ($versaoNova && $conteudo->exists && $conteudo->estado === 'publicado') {
                throw new ConflictHttpException("{$conteudo->nome} já foi publicado: use outro nome para um conteúdo novo.");
            }
            if ($conteudo->exists && (int) $dados['versao'] < $conteudo->versao) {
                throw new ConflictHttpException("A Central já tem a versão {$conteudo->versao} de {$conteudo->nome}.");
            }

            // Já decidido (aprovado, ajuste, tirado, publicado): a mesma versão não muda mais. Lâminas novas exigem
            // subir a versão (e aí volta para aprovação). Só a informação de publicado é aceita.
            if (! $versaoNova && $conteudo->estado !== 'aguardando') {
                if ($imagens !== [] || $pdf !== null) {
                    throw new ConflictHttpException("{$conteudo->nome} v{$conteudo->versao} já foi decidido: suba a versão para mandar lâminas novas.");
                }
                if (! empty($dados['publicado']['link'])) {
                    $conteudo->forceFill(['estado' => 'publicado', 'link' => $dados['publicado']['link'],
                        'publicado_em' => $dados['publicado']['em'] ?? now()])->save();
                }

                return $conteudo->fresh();
            }

            if ($versaoNova && $tipo === 'carrossel' && $imagens === []) {
                throw ValidationException::withMessages(['laminas' => 'Versão nova precisa vir com as lâminas.']);
            }
            if ($versaoNova && $tipo === 'reels' && empty($dados['roteiro'])) {
                throw ValidationException::withMessages(['roteiro' => 'Versão nova do Reels precisa vir com o roteiro.']);
            }
            // Mesma versão de um Reels que já está no vídeo: o roteiro não volta por aqui (o vídeo chega por enviarVideo).
            if (! $versaoNova && $conteudo->ehReels() && $conteudo->fase === 'video') {
                throw new ConflictHttpException("{$conteudo->nome} v{$conteudo->versao} já é o vídeo: suba a versão para mandar um roteiro novo.");
            }

            $item = isset($dados['calendario_chave'])
                ? CalendarioItem::where('marca_id', $marca->id)->where('chave', $dados['calendario_chave'])->first()
                : null;

            $conteudo->fill([
                'titulo' => $dados['titulo'],
                'calendario_chave' => $dados['calendario_chave'] ?? null,
                'agendado_para' => $this->quando($dados, $item),
                'publico' => $dados['publico'] ?? [],
                'legenda_instagram' => $dados['instagram'] ?? null,
                'texto_linkedin' => $dados['linkedin'] ?? null,
                'conferencia' => $dados['conferencia'] ?? [],
                'fontes' => $dados['fontes'] ?? [],
                'versao' => (int) $dados['versao'],
                'tipo' => $tipo,
            ]);
            if ($tipo === 'reels') {
                $conteudo->fill(['fase' => 'roteiro', 'roteiro' => array_values($dados['roteiro'] ?? $conteudo->roteiro ?? []),
                    'duracao_segundos' => $dados['duracao_segundos'] ?? $conteudo->duracao_segundos]);
            }

            if ($versaoNova) {
                $conteudo->fill(['estado' => 'aguardando', 'decisao' => null, 'nota' => null, 'versao_decidida' => null,
                    'decidido_em' => null, 'ajuste_feito' => $dados['ajuste_feito'] ?? null]);
                if ($tipo === 'reels') {
                    // Roteiro novo: a aprovação do roteiro anterior e o vídeo dele não valem mais.
                    $conteudo->fill(['roteiro_aprovado_versao' => null, 'video_caminho' => null, 'video_hash' => null, 'capa_caminho' => null]);
                }
            }

            if (! empty($dados['publicado']['link'])) {
                $conteudo->fill(['estado' => 'publicado', 'link' => $dados['publicado']['link'],
                    'publicado_em' => $dados['publicado']['em'] ?? now()]);
            }

            $conteudo->save();

            if ($imagens !== []) {
                $this->gravarLaminas($marca, $conteudo, $imagens);
            }
            if ($pdf !== null) {
                $caminho = "conteudos/{$marca->slug}/{$conteudo->nome}/v{$conteudo->versao}/linkedin.pdf";
                Storage::put($caminho, $pdf);
                $conteudo->forceFill(['pdf_caminho' => $caminho])->save();
            }
            if ($item) {
                $this->marcarProduzido($item, $conteudo);
            }

            return $conteudo->fresh();
        });
    }

    /**
     * Vídeo pronto do Reels (segunda aprovação). Só entra depois do roteiro aprovado, sempre numa versão nova
     * (ou a mesma versão ainda sem decisão), e com a impressão digital que o kit calculou: arquivo cortado no
     * caminho não passa.
     */
    public function receberVideo(Marca $marca, Conteudo $conteudo, array $dados, UploadedFile $video, ?UploadedFile $capa): Conteudo
    {
        if (! $conteudo->ehReels()) {
            throw new ConflictHttpException("{$conteudo->nome} não é um Reels.");
        }
        $this->conferirArquivo($video, 'video', "\x66\x74\x79\x70", 4, self::MAX_BYTES_VIDEO, 'O vídeo precisa ser MP4.');
        $marcaMp4 = substr((string) file_get_contents($video->getRealPath(), false, null, 8, 4), 0, 4);
        if (! preg_match('/^(isom|iso[2-9]|mp41|mp42|avc1|dash|M4V )$/', $marcaMp4)) {
            throw ValidationException::withMessages(['video' => 'O vídeo precisa ser MP4 (H.264).']);
        }
        if ($capa) {
            $this->conferirArquivo($capa, 'capa', "\xFF\xD8\xFF", 0, self::MAX_BYTES_IMAGEM, 'A capa precisa ser JPEG.');
        }
        $hash = hash_file('sha256', $video->getRealPath());
        if (! hash_equals(strtolower($dados['sha256']), $hash)) {
            throw ValidationException::withMessages(['video' => 'O vídeo chegou diferente do que saiu do kit. Mande de novo.']);
        }

        return DB::transaction(function () use ($marca, $conteudo, $dados, $video, $capa, $hash) {
            $conteudo = Conteudo::whereKey($conteudo->id)->lockForUpdate()->first();
            $versao = (int) $dados['versao'];
            if (! $conteudo->roteiro_aprovado_versao) {
                throw new ConflictHttpException('O roteiro deste Reels ainda não foi aprovado.');
            }
            if (in_array($conteudo->estado, ['postando', 'publicado'], true)) {
                throw new ConflictHttpException("{$conteudo->nome} já foi publicado ou está sendo postado.");
            }
            if ($conteudo->estado === 'tirado') {
                throw new ConflictHttpException("{$conteudo->nome} foi tirado na Central.");
            }
            $mesmaSemDecisao = $versao === $conteudo->versao && $conteudo->fase === 'video' && $conteudo->estado === 'aguardando';
            if ($versao <= $conteudo->versao && ! $mesmaSemDecisao) {
                throw new ConflictHttpException("Suba a versão: a Central já tem a versão {$conteudo->versao} de {$conteudo->nome}.");
            }

            $pasta = "conteudos/{$marca->slug}/{$conteudo->nome}/v{$versao}";
            Storage::putFileAs($pasta, $video, 'reels.mp4');
            $capaCaminho = $capa ? Storage::putFileAs($pasta, $capa, 'capa.jpg') : null;

            $conteudo->forceFill([
                'versao' => $versao, 'fase' => 'video', 'estado' => 'aguardando', 'decisao' => null, 'nota' => null,
                'versao_decidida' => null, 'decidido_em' => null, 'ajuste_feito' => $dados['ajuste_feito'] ?? null,
                'video_caminho' => "{$pasta}/reels.mp4", 'video_hash' => $hash, 'capa_caminho' => $capaCaminho,
                'duracao_segundos' => $dados['duracao_segundos'] ?? $conteudo->duracao_segundos,
                'legenda_instagram' => $dados['instagram'] ?? $conteudo->legenda_instagram,
            ])->save();

            $item = $conteudo->calendario_chave
                ? CalendarioItem::where('marca_id', $marca->id)->where('chave', $conteudo->calendario_chave)->first() : null;
            if ($item) {
                $this->marcarProduzido($item, $conteudo);
            }

            return $conteudo->fresh();
        });
    }

    /** Chegou o conteúdo: o tema está produzido e o pedido de produção (se houve) acabou. */
    private function marcarProduzido(CalendarioItem $item, Conteudo $conteudo): void
    {
        $item->forceFill(['carrossel' => $conteudo->nome, 'producao_pedida_em' => null, 'producao_iniciada_em' => null,
            'estado' => $item->podeMexer() ? 'produzido' : $item->estado])->save();
    }

    private function conferirArquivo(UploadedFile $arquivo, string $campo, string $assinatura, int $inicio, int $max, string $erro): void
    {
        if ($arquivo->getSize() > $max) {
            throw ValidationException::withMessages([$campo => 'Arquivo grande demais.']);
        }
        $cabeca = (string) file_get_contents($arquivo->getRealPath(), false, null, 0, 16);
        if (substr($cabeca, $inicio, strlen($assinatura)) !== $assinatura) {
            throw ValidationException::withMessages([$campo => $erro]);
        }
    }

    private function quando(array $dados, ?CalendarioItem $item): ?CarbonImmutable
    {
        $data = $dados['data'] ?? $item?->data?->toDateString();
        $hora = $dados['hora'] ?? $item?->hora ?? '11:40';

        return $data ? CarbonImmutable::parse("{$data} {$hora}", config('app.timezone')) : null;
    }

    /** @return list<string> */
    private function decodificarLaminas(array $laminas): array
    {
        if (count($laminas) > self::MAX_LAMINAS) {
            throw ValidationException::withMessages(['laminas' => 'No máximo '.self::MAX_LAMINAS.' lâminas.']);
        }

        return array_map(fn ($b64) => $this->decodificar((string) $b64, "\xFF\xD8\xFF", self::MAX_BYTES_IMAGEM, 'laminas'), array_values($laminas));
    }

    private function decodificar(string $base64, string $assinatura, int $max, string $campo): string
    {
        $bytes = base64_decode($base64, true);
        if ($bytes === false || ! str_starts_with($bytes, $assinatura)) {
            throw ValidationException::withMessages([$campo => $campo === 'pdf' ? 'O arquivo não é um PDF.' : 'Lâmina precisa ser JPEG.']);
        }
        if (strlen($bytes) > $max) {
            throw ValidationException::withMessages([$campo => 'Arquivo grande demais.']);
        }

        return $bytes;
    }

    private function gravarLaminas(Marca $marca, Conteudo $conteudo, array $imagens): void
    {
        $pasta = "conteudos/{$marca->slug}/{$conteudo->nome}/v{$conteudo->versao}";
        Storage::deleteDirectory($pasta);
        $conteudo->forceFill(['hash_laminas' => Conteudo::hashDasLaminas($imagens)])->save();
        $conteudo->laminas()->where('versao', $conteudo->versao)->delete();

        foreach ($imagens as $i => $bytes) {
            $caminho = sprintf('%s/%02d.jpg', $pasta, $i + 1);
            Storage::put($caminho, $bytes);
            $conteudo->laminas()->create(['versao' => $conteudo->versao, 'ordem' => $i + 1, 'caminho' => $caminho]);
        }
    }
}
