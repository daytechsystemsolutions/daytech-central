<?php

namespace App\Console\Commands;

use App\Models\TokenFabrica;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Cria a chave da fábrica e escreve na saída as linhas do ~/.config/mednext/central.env do kit.
 * Uso (do PC, sem a chave passar pela tela):
 *   ssh ... 'docker exec central_app php artisan central:token-fabrica kit-pc' > ~/.config/mednext/central.env
 */
class CentralTokenFabrica extends Command
{
    protected $signature = 'central:token-fabrica {nome=kit : de onde a chave vai ser usada}
        {--site : chave do site (só avisa lead; imprime CENTRAL_LEADS_URL e CENTRAL_LEADS_TOKEN)}
        {--revogar-todos : apaga todas as chaves do mesmo tipo antes}';

    protected $description = 'Cria a chave de acesso da fábrica de conteúdo (imprime CENTRAL_URL e CENTRAL_TOKEN)';

    public function handle(): int
    {
        $escopo = $this->option('site') ? 'site' : 'fabrica';
        if ($this->option('revogar-todos')) {
            TokenFabrica::where('escopo', $escopo)->delete();
        }

        $token = Str::random(64);
        TokenFabrica::create(['nome' => (string) $this->argument('nome'), 'hash' => TokenFabrica::hashDe($token), 'escopo' => $escopo]);

        $url = rtrim((string) config('app.url'), '/');
        if ($escopo === 'site') {
            $this->line('CENTRAL_LEADS_URL='.$url.'/api/site/marcas/mednext/leads');
            $this->line('CENTRAL_LEADS_TOKEN='.$token);

            return self::SUCCESS;
        }
        $this->line('CENTRAL_URL='.$url);
        $this->line('CENTRAL_TOKEN='.$token);

        return self::SUCCESS;
    }
}
