<?php

namespace App\Services\Conteudo;

use App\Models\CalendarioItem;
use App\Models\Conteudo;
use App\Models\Marca;
use Carbon\CarbonImmutable;
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

    public function receber(Marca $marca, array $dados): Conteudo
    {
        $imagens = $this->decodificarLaminas($dados['laminas'] ?? []);
        $pdf = isset($dados['pdf']) ? $this->decodificar($dados['pdf'], '%PDF', self::MAX_BYTES_PDF, 'pdf') : null;

        return DB::transaction(function () use ($marca, $dados, $imagens, $pdf) {
            $conteudo = Conteudo::where('marca_id', $marca->id)->where('nome', $dados['nome'])->lockForUpdate()->first()
                ?? new Conteudo(['marca_id' => $marca->id, 'nome' => $dados['nome']]);
            $versaoNova = ! $conteudo->exists || (int) $dados['versao'] > $conteudo->versao;

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

            if ($versaoNova && $imagens === []) {
                throw ValidationException::withMessages(['laminas' => 'Versão nova precisa vir com as lâminas.']);
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
            ]);

            if ($versaoNova) {
                $conteudo->fill(['estado' => 'aguardando', 'decisao' => null, 'nota' => null, 'versao_decidida' => null,
                    'decidido_em' => null, 'ajuste_feito' => $dados['ajuste_feito'] ?? null]);
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
                // Chegou o carrossel: o tema está produzido e o pedido de produção (se houve) acabou.
                $item->forceFill(['carrossel' => $conteudo->nome, 'producao_pedida_em' => null, 'producao_iniciada_em' => null,
                    'estado' => $item->podeMexer() ? 'produzido' : $item->estado])->save();
            }

            return $conteudo->fresh();
        });
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
