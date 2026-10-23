<?php

namespace Tests\Feature;

use App\Models\ContaDia;
use App\Models\Marca;
use App\Models\Post;
use App\Models\PostMetrica;
use App\Models\User;
use App\Services\Resultados\NotaDoPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('central:preparar');
        $this->marca = Marca::where('slug', 'mednext')->first();
    }

    private function criarPost(string $id, int $diasAtras, array $numeros, string $tipo = 'CAROUSEL_ALBUM'): Post
    {
        $post = Post::create(['marca_id' => $this->marca->id, 'ig_id' => $id, 'tipo_midia' => $tipo, 'publicado_em' => now()->subDays($diasAtras)]);
        PostMetrica::create(['post_id' => $post->id, 'dia' => today()] + $numeros);

        return $post;
    }

    private function media(string $id, int $diasAtras = 40): Post
    {
        return $this->criarPost($id, $diasAtras, ['alcance' => 100, 'salvos' => 1, 'compartilhamentos' => 1, 'novos_seguidores' => 1, 'visitas_perfil' => 1, 'acoes_perfil' => 1]);
    }

    public function test_na_media_vale_50_e_o_dobro_vale_100(): void
    {
        foreach (range(1, 5) as $n) {
            $this->media("m{$n}");
        }
        $igual = $this->criarPost('igual', 10, ['alcance' => 100, 'salvos' => 1, 'compartilhamentos' => 1, 'novos_seguidores' => 1, 'visitas_perfil' => 1, 'acoes_perfil' => 1]);
        $dobro = $this->criarPost('dobro', 10, ['alcance' => 200, 'salvos' => 4, 'compartilhamentos' => 4, 'novos_seguidores' => 4, 'visitas_perfil' => 4, 'acoes_perfil' => 4]);

        app(NotaDoPost::class)->atualizar($this->marca);

        $this->assertSame(50, $igual->fresh()->nota);
        $this->assertSame(100, $dobro->fresh()->nota);
        $this->assertNotNull($dobro->fresh()->nota_fechada_em, 'passou de 7 dias: fecha');
        $this->assertSame(['ótimo', 'espera'], $dobro->fresh()->faixa());
    }

    public function test_video_sem_crescimento_e_interesse_usa_so_as_partes_que_tem(): void
    {
        foreach (range(1, 5) as $n) {
            $this->media("m{$n}");
        }
        $video = $this->criarPost('v', 10, ['alcance' => 100, 'salvos' => 1, 'compartilhamentos' => 1], 'VIDEO');

        app(NotaDoPost::class)->atualizar($this->marca);

        $partes = $video->fresh()->nota_partes['partes'];
        $this->assertSame(['alcance', 'valor'], array_keys($partes));
        $this->assertSame(50, $video->fresh()->nota);
    }

    public function test_parcial_antes_do_7o_dia_e_fechada_nao_muda_mais(): void
    {
        foreach (range(1, 5) as $n) {
            $this->media("m{$n}");
        }
        $novo = $this->criarPost('novo', 2, ['alcance' => 100, 'salvos' => 1, 'compartilhamentos' => 1, 'novos_seguidores' => 1, 'visitas_perfil' => 1, 'acoes_perfil' => 1]);
        app(NotaDoPost::class)->atualizar($this->marca);
        $this->assertNull($novo->fresh()->nota_fechada_em);

        $fechado = Post::where('ig_id', 'm1')->first();
        $nota = $fechado->nota;
        PostMetrica::where('post_id', $fechado->id)->update(['alcance' => 9999]);
        app(NotaDoPost::class)->atualizar($this->marca);
        $this->assertSame($nota, $fechado->fresh()->nota);
    }

    public function test_fase_muda_os_pesos_em_500_seguidores_ou_a_mao(): void
    {
        $this->assertSame('conhecido', $this->marca->faseAtual());
        ContaDia::create(['marca_id' => $this->marca->id, 'dia' => today(), 'seguidores' => 510]);
        $this->assertSame('clientes', $this->marca->fresh()->faseAtual());
        $this->marca->forceFill(['fase' => 'conhecido'])->save();
        $this->assertSame('conhecido', $this->marca->fresh()->faseAtual());
    }

    public function test_inicio_mostra_os_indicadores_da_conta(): void
    {
        ContaDia::create(['marca_id' => $this->marca->id, 'dia' => today()->subDays(9), 'seguidores' => 79, 'posts' => 29]);
        ContaDia::create(['marca_id' => $this->marca->id, 'dia' => today()->subDay(), 'seguidores' => 82, 'posts' => 30, 'alcance' => 130, 'interacoes' => 7, 'visitas_perfil' => 2, 'toques_link' => 0]);
        $usuario = User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);

        $this->actingAs($usuario)->get('/')->assertOk()
            ->assertSee('Sua conta')->assertSee('82')->assertSee('+3 na semana')->assertSee('130')->assertSee('7 interações');
    }
}
