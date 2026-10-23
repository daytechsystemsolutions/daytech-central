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

    /**
     * GET na API com o token no cabeçalho. Devolve o JSON ou lança InstagramErro (mensagem sem token).
     *
     * @throws InstagramErro
     */
    public function get(string $token, string $caminho, array $campos = []): array
    {
        if (str_starts_with($caminho, 'https://')) {
            // A query vai separada: o cliente HTTP troca a query da URL pela de $campos.
            [$url, $campos] = $this->urlDaApi($caminho, $campos);
        } else {
            $url = "{$this->base}/".ltrim($caminho, '/');
        }
        try {
            $resposta = Http::timeout(30)->acceptJson()->withToken($token)->get($url, $campos);
        } catch (ConnectionException) {
            throw new InstagramErro('Não consegui falar com o Instagram.');
        }
        if ($resposta->failed()) {
            $mensagem = (string) $resposta->json('error.message', 'resposta inesperada');
            $codigo = $resposta->json('error.code');
            throw new InstagramErro('Instagram: '.Str::limit(str_replace($token, '***', $mensagem), 160), is_numeric($codigo) ? (int) $codigo : null);
        }

        return (array) $resposta->json();
    }

    /**
     * URL absoluta (a "próxima página" que o próprio Graph devolve): só do mesmo host da API, e sem o access_token
     * que o Graph costuma pôr na query (o token vai só no cabeçalho).
     */
    /** @return array{0: string, 1: array} URL sem query e os parâmetros (sem access_token) */
    private function urlDaApi(string $url, array $campos): array
    {
        $partes = parse_url($url);
        if (($partes['host'] ?? '') !== parse_url($this->base, PHP_URL_HOST)) {
            throw new InstagramErro('Endereço de paginação inesperado.');
        }
        parse_str($partes['query'] ?? '', $query);
        unset($query['access_token']);

        return ['https://'.$partes['host'].($partes['path'] ?? ''), $query + $campos];
    }

    /**
     * POST na API (criar contêiner, publicar). Token no cabeçalho, como no GET.
     *
     * @throws InstagramErro
     */
    public function post(string $token, string $caminho, array $campos): array
    {
        try {
            $resposta = Http::timeout(60)->acceptJson()->asForm()->withToken($token)->post("{$this->base}/".ltrim($caminho, '/'), $campos);
        } catch (ConnectionException) {
            throw new InstagramErro('Não consegui falar com o Instagram.');
        }
        if ($resposta->failed()) {
            $mensagem = (string) $resposta->json('error.message', 'resposta inesperada');
            $codigo = $resposta->json('error.code');
            throw new InstagramErro('Instagram: '.Str::limit(str_replace($token, '***', $mensagem), 200), is_numeric($codigo) ? (int) $codigo : null);
        }

        return (array) $resposta->json();
    }

    /** Segue a paginação ("paging.next") e junta tudo de "data". */
    public function todos(string $token, string $caminho, array $campos = [], int $maxPaginas = 20): array
    {
        $itens = [];
        $pagina = $this->get($token, $caminho, $campos);
        for ($n = 0; ; $n++) {
            array_push($itens, ...($pagina['data'] ?? []));
            $proxima = $pagina['paging']['next'] ?? null;
            if (! $proxima || $n + 1 >= $maxPaginas) {
                return $itens;
            }
            $pagina = $this->get($token, $proxima);
        }
    }

    /**
     * Insights de um post. Pede tudo de uma vez; se o Instagram recusar (post antigo ou vídeo não aceita algum
     * número), pede um por um e guarda só o que vier.
     *
     * @return array<string, int> nome na API => valor
     */
    public function insightsDoPost(string $token, string $midiaId, array $metricas): array
    {
        try {
            return $this->valores($this->get($token, "{$midiaId}/insights", ['metric' => implode(',', $metricas)]));
        } catch (InstagramErro $e) {
            // Token vencido, limite de chamadas, rede: sobe (a coleta para e a marca mostra o erro).
            if (! $e->naoSuportado()) {
                throw $e;
            }
            $valores = [];
            foreach ($metricas as $metrica) {
                try {
                    $valores += $this->valores($this->get($token, "{$midiaId}/insights", ['metric' => $metrica]));
                } catch (InstagramErro $e) {
                    if (! $e->naoSuportado()) {
                        throw $e;
                    }
                    // número que este post não tem: fica em branco
                }
            }

            return $valores;
        }
    }

    /** @return array<string, int> */
    private function valores(array $resposta): array
    {
        $saida = [];
        foreach ($resposta['data'] ?? [] as $item) {
            $valor = $item['values'][0]['value'] ?? $item['total_value']['value'] ?? null;
            if (is_numeric($valor)) {
                $saida[$item['name']] = (int) $valor;
            }
        }

        return $saida;
    }

    /**
     * Baixa a capa do post (os links de imagem do Instagram expiram, por isso a capa é guardada). Só dos servidores
     * de imagem da Meta, sem seguir redirecionamento e até 10 MB.
     */
    public function baixar(string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! preg_match('/(^|\.)(cdninstagram\.com|fbcdn\.net)$/', $host)) {
            return null;
        }
        try {
            $resposta = Http::timeout(30)->withOptions(['allow_redirects' => false])->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $resposta->successful() && strlen($resposta->body()) <= 10 * 1024 * 1024 ? $resposta->body() : null;
    }
}
