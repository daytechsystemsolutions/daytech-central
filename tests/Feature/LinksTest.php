<?php

namespace Tests\Feature;

use App\Models\Conteudo;
use App\Models\Lead;
use App\Models\Marca;
use App\Models\Post;
use App\Models\TokenFabrica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinksTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('central:preparar');
        $this->marca = Marca::where('slug', 'mednext')->first();
        TokenFabrica::create(['nome' => 'site', 'hash' => TokenFabrica::hashDe(str_repeat('s', 64)), 'escopo' => 'site']);
        TokenFabrica::create(['nome' => 'kit', 'hash' => TokenFabrica::hashDe(str_repeat('k', 64)), 'escopo' => 'fabrica']);
    }

    private function publicado(array $extra = []): Conteudo
    {
        return Conteudo::create(array_merge(['marca_id' => $this->marca->id, 'nome' => 'afrp-celular', 'titulo' => 'Avaliação psicossocial',
            'versao' => 2, 'estado' => 'publicado', 'publicado_em' => now()->subDay(), 'destino' => '/modulos/afrp',
            'calendario_chave' => '2026-11-03-reels'], $extra));
    }

    public function test_link_curto_conta_o_clique_e_leva_ao_site_com_a_utm_do_post(): void
    {
        $c = $this->publicado();
        $codigo = $c->garantirCodigoLink();
        $this->assertSame($codigo, $c->fresh()->garantirCodigoLink(), 'o código não muda');

        $r = $this->get("/l/{$codigo}?m=story")->assertRedirect();
        $destino = $r->headers->get('Location');
        $this->assertStringStartsWith('https://mednextsst.com/modulos/afrp?', $destino);
        parse_str(parse_url($destino, PHP_URL_QUERY), $utm);
        $this->assertSame(['utm_source' => 'instagram', 'utm_medium' => 'story', 'utm_campaign' => '2026-11-03-reels', 'utm_content' => 'afrp-celular'], $utm);

        $this->get("/l/{$codigo}?m=qualquer")->assertRedirect();
        $this->assertSame(['story', 'bio'], $c->cliques()->orderBy('id')->pluck('meio')->all(), 'meio desconhecido vira bio');

        // Código que não existe: vai para a home do site, sem erro.
        $this->get('/l/naoexiste')->assertRedirect('https://mednextsst.com/');
    }

    public function test_site_lista_os_posts_publicados_com_a_chave_do_site(): void
    {
        $this->publicado();
        $this->publicado(['nome' => 'agendado', 'estado' => 'agendado', 'publicado_em' => null]);
        $url = '/api/site/marcas/mednext/links';

        $this->getJson($url)->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('k', 64)])->getJson($url)->assertUnauthorized();
        $r = $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('s', 64)])->getJson($url)->assertOk();

        $r->assertJsonCount(1, 'posts')->assertJsonFragment(['titulo' => 'Avaliação psicossocial', 'destino' => '/modulos/afrp', 'tipo' => 'carrossel']);
        $this->assertStringContainsString('/l/', $r->json('posts.0.link'));
    }

    public function test_kit_manda_o_destino_e_recebe_o_link(): void
    {
        $kit = ['Authorization' => 'Bearer '.str_repeat('k', 64)];
        $jpeg = base64_encode("\xFF\xD8\xFF\xE0lamina");
        $this->withHeaders($kit)->postJson('/api/fabrica/marcas/mednext/conteudos', ['nome' => 'trocou', 'titulo' => 'Trocou de sistema',
            'versao' => 1, 'laminas' => [$jpeg], 'destino' => 'javascript:alert(1)'])->assertStatus(422);
        $this->withHeaders($kit)->postJson('/api/fabrica/marcas/mednext/conteudos', ['nome' => 'trocou', 'titulo' => 'Trocou de sistema',
            'versao' => 1, 'laminas' => [$jpeg], 'destino' => '/modulos/sst'])->assertOk();

        $this->assertSame('/modulos/sst', Conteudo::where('nome', 'trocou')->value('destino'));
        $this->withHeaders($kit)->getJson('/api/fabrica/marcas/mednext/conteudos/decisoes')->assertJsonFragment(['id' => 'trocou'])
            ->assertJsonPath('0.link', fn ($l) => str_contains($l, '/l/'));
    }

    public function test_resultado_do_post_mostra_cliques_e_leads_dele(): void
    {
        $c = $this->publicado();
        $c->cliques()->createMany([['meio' => 'bio', 'clicado_em' => now()], ['meio' => 'story', 'clicado_em' => now()]]);
        Lead::create(['marca_id' => $this->marca->id, 'recebido_em' => now(), 'fonte' => 'instagram', 'meio' => 'bio', 'conteudo' => 'afrp-celular']);
        Lead::create(['marca_id' => $this->marca->id, 'recebido_em' => now(), 'fonte' => 'instagram', 'meio' => 'bio', 'conteudo' => 'outro']);
        $post = Post::create(['marca_id' => $this->marca->id, 'ig_id' => 'p1', 'conteudo_id' => $c->id, 'tipo_midia' => 'CAROUSEL_ALBUM', 'publicado_em' => now()->subDay()]);
        $c->garantirCodigoLink();

        $u = User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);
        $this->actingAs($u)->get("/resultados/{$post->id}")->assertOk()->assertSee('Do post ao site')
            ->assertSeeInOrder(['2', 'cliques no link', '1', 'leads deste post'])->assertSee('?m=story');
    }
}
