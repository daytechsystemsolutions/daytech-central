<?php

namespace App\Services\Instagram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * API do Instagram pelo login do Facebook (graph.facebook.com), a mesma do kit de conteúdo.
 * O token vai só no pedido; nunca em log nem em mensagem de erro.
 */
class InstagramClient
{
    public function __construct(
        private readonly string $base = 'https://graph.facebook.com/v23.0',
    ) {}

    /**
     * Confere se o token enxerga a conta e devolve o @ dela.
     *
     * @throws InstagramErro
     */
    public function conferirConta(string $contaId, string $token): string
    {
        if (! preg_match('/^\d{5,30}$/', $contaId)) {
            throw new InstagramErro('O ID da conta do Instagram tem que ser só números.');
        }

        try {
            // Token no cabeçalho (não na URL): uma exceção com a URL nunca leva o token para o log.
            $resposta = Http::timeout(15)->acceptJson()->withToken($token)
                ->get("{$this->base}/{$contaId}", ['fields' => 'id,username']);
        } catch (ConnectionException) {
            throw new InstagramErro('Não consegui falar com o Instagram. Tente de novo em alguns minutos.');
        }

        if ($resposta->failed()) {
            $mensagem = (string) $resposta->json('error.message', 'resposta inesperada');
            throw new InstagramErro('O Instagram recusou: '.Str::limit(str_replace($token, '***', $mensagem), 160));
        }

        $usuario = (string) $resposta->json('username', '');
        if ($usuario === '' || (string) $resposta->json('id') !== $contaId) {
            throw new InstagramErro('O Instagram respondeu, mas sem o nome da conta. Confira o ID da conta.');
        }

        return $usuario;
    }
}
