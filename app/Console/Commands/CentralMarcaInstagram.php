<?php

namespace App\Console\Commands;

use App\Models\Marca;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramErro;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Liga o Instagram de uma marca. O token é digitado aqui (não aparece na tela nem no histórico),
 * conferido na API e guardado criptografado.
 */
class CentralMarcaInstagram extends Command
{
    protected $signature = 'central:marca-instagram {marca : slug da marca, ex.: mednext}
        {--env-stdin : lê INSTAGRAM_USER_ID e INSTAGRAM_ACCESS_TOKEN da entrada (o instagram.env do kit), sem perguntar}';

    protected $description = 'Conecta o Instagram de uma marca (pede o token sem mostrar)';

    public function handle(InstagramClient $instagram): int
    {
        $marca = Marca::where('slug', $this->argument('marca'))->first();
        if (! $marca) {
            $this->error('Marca não encontrada. Rode antes: php artisan central:preparar');

            return self::FAILURE;
        }

        if ($this->option('env-stdin')) {
            [$contaId, $token] = $this->lerDaEntrada();
            if ($contaId === '' || $token === '') {
                $this->error('Faltou INSTAGRAM_USER_ID ou INSTAGRAM_ACCESS_TOKEN na entrada.');

                return self::FAILURE;
            }
        } else {
            $contaId = text('ID da conta do Instagram (só números)', default: (string) $marca->instagram_conta_id, required: true);
            $token = password('Token de acesso (não aparece enquanto você cola)', required: true);
        }

        try {
            $usuario = $instagram->conferirConta(trim($contaId), trim($token));
        } catch (InstagramErro $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $marca->forceFill([
            'instagram_conta_id' => trim($contaId),
            'instagram_token' => trim($token),
            'instagram_usuario' => $usuario,
            'instagram_verificado_em' => now(),
            'instagram_erro' => null,
        ])->save();

        $this->info("Instagram conectado: @{$usuario} ({$marca->nome})");

        return self::SUCCESS;
    }

    /** Linhas CHAVE=valor (formato .env), como o ~/.config/mednext/instagram.env do kit. */
    private function lerDaEntrada(): array
    {
        $valores = [];
        while (($linha = fgets(STDIN)) !== false) {
            if (preg_match('/^\s*([A-Z_]+)\s*=\s*"?([^"\r\n]*)"?\s*$/', $linha, $m)) {
                $valores[$m[1]] = trim($m[2]);
            }
        }

        return [$valores['INSTAGRAM_USER_ID'] ?? '', $valores['INSTAGRAM_ACCESS_TOKEN'] ?? ''];
    }
}
