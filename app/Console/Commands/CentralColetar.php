<?php

namespace App\Console\Commands;

use App\Models\Marca;
use App\Services\Instagram\ColetaInstagram;
use App\Services\Instagram\InstagramErro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coleta do Instagram (rodada pelo agendador da VPS; ver routes/console.php).
 *   conta         seguidores, posts e o dia anterior da conta
 *   posts         posts novos do perfil + números dos posts de até 30 dias (com --todos: todos os posts)
 *   semanal       números dos posts de 31 a 90 dias
 *   comentarios   comentários novos dos posts dos últimos 14 dias
 */
class CentralColetar extends Command
{
    protected $signature = 'central:coletar {o=tudo : conta|posts|semanal|comentarios|tudo} {--marca= : só esta marca} {--todos : números de todos os posts (importação)}
        {--se-vazio : só marcas que ainda não têm posts importados (primeiro deploy)}';

    protected $description = 'Busca no Instagram os números da conta, dos posts e os comentários';

    public function handle(ColetaInstagram $coleta): int
    {
        $marcas = Marca::ativas()->get()->filter->instagramConectado()
            ->when($this->option('marca'), fn ($c) => $c->where('slug', $this->option('marca')))
            // Primeiro deploy: só quem ainda não tem números (se uma importação caiu no meio, refaz).
            ->when($this->option('se-vazio'), fn ($c) => $c->filter(fn (Marca $m) => ! \App\Models\PostMetrica::whereIn('post_id', $m->posts()->select('id'))->exists()));
        $o = $this->argument('o');
        $falhou = false;

        // Uma coleta por vez (agendador e deploy usam o mesmo cadeado).
        $cadeado = Cache::lock('central-coletar', 1800);
        if (! $cadeado->get()) {
            $this->line('• outra coleta em andamento; esta não roda');

            return self::SUCCESS;
        }

        foreach ($marcas as $marca) {
            try {
                if (in_array($o, ['conta', 'tudo'], true)) {
                    $dia = $coleta->conta($marca);
                    $this->line("✓ {$marca->slug} conta: {$dia->seguidores} seguidores, {$dia->posts} posts, alcance de ontem {$dia->alcance}");
                }
                if (in_array($o, ['posts', 'tudo'], true)) {
                    $novos = $coleta->posts($marca);
                    $alvo = $this->option('todos') ? $marca->posts()->get() : $marca->posts()->where('publicado_em', '>=', now()->subDays(30))->get();
                    $lidos = $coleta->metricas($marca, $alvo);
                    $this->line("✓ {$marca->slug} posts: {$novos} novo(s), números de {$lidos} post(s)");
                }
                if ($o === 'semanal') {
                    $alvo = $marca->posts()->whereBetween('publicado_em', [now()->subDays(90), now()->subDays(30)])->get();
                    $this->line("✓ {$marca->slug} semanal: números de {$coleta->metricas($marca, $alvo)} post(s)");
                }
                if (in_array($o, ['comentarios', 'tudo'], true)) {
                    $alvo = $marca->posts()->where('publicado_em', '>=', now()->subDays(14))->get();
                    $this->line("✓ {$marca->slug} comentários: {$coleta->comentarios($marca, $alvo)} novo(s)");
                }
            } catch (InstagramErro $e) {
                $marca->forceFill(['instagram_erro' => $e->getMessage()])->save();
                $this->error("✗ {$marca->slug}: {$e->getMessage()}");
                $falhou = true;
            } catch (Throwable $e) {
                // Erro inesperado numa marca não impede a coleta das outras.
                Log::error("coleta {$marca->slug}", ['erro' => $e->getMessage()]);
                $this->error("✗ {$marca->slug}: erro inesperado ({$e->getMessage()})");
                $falhou = true;
            }
        }
        $cadeado->release();

        return $falhou ? self::FAILURE : self::SUCCESS;
    }
}
