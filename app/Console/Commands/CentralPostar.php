<?php

namespace App\Console\Commands;

use App\Models\Marca;
use App\Services\Instagram\InstagramErro;
use App\Services\Postagem\PostagemInstagram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Posta o carrossel aprovado cujo horário chegou (um por marca a cada rodada; o agendador roda a cada 5 minutos). */
class CentralPostar extends Command
{
    protected $signature = 'central:postar';

    protected $description = 'Posta no Instagram os carrosséis aprovados cujo horário chegou';

    public function handle(PostagemInstagram $postagem): int
    {
        $cadeado = Cache::lock('central-postar', 1800);
        if (! $cadeado->get()) {
            return self::SUCCESS;
        }

        try {
            foreach (Marca::ativas()->get() as $marca) {
                $postagem->marcarPerdidos($marca);
                $conteudo = $postagem->proximo($marca);
                if (! $conteudo) {
                    continue;
                }
                try {
                    // O serviço já grava "falhou" (só se nada saiu) ou "publicado"; não tenta de novo sozinho.
                    $feito = $postagem->postar($conteudo);
                    if ($feito) {
                        $this->info("✓ {$marca->slug}: {$feito->nome} publicado ({$feito->link})");
                    }
                } catch (InstagramErro $e) {
                    $this->error("✗ {$marca->slug}: {$conteudo->nome} não publicado: {$e->getMessage()}");
                } catch (Throwable $e) {
                    Log::error('postagem', ['conteudo' => $conteudo->id, 'erro' => $e->getMessage()]);
                    $this->error("✗ {$marca->slug}: {$conteudo->nome}: erro inesperado");
                }
            }
        } finally {
            $cadeado->release();
        }

        return self::SUCCESS;
    }
}
