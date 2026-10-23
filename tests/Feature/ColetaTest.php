<?php

namespace Tests\Feature;

use App\Models\ContaDia;
use App\Models\Conteudo;
use App\Models\Marca;
use App\Models\Post;
use App\Models\PostMetrica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ColetaTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG = "\xFF\xD8\xFF\xE0capa";

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->artisan('central:preparar');
        $this->marca = Marca::where('slug', 'mednext')->first();
        $this->marca->forceFill(['instagram_conta_id' => '1784', 'instagram_token' => 'TOKEN-X', 'instagram_usuario' => 'mednext_sst', 'instagram_verificado_em' => now()])->save();
    }

    private function fingirInstagram(): void
    {
        Http::fake(function (Request $r) {
            $url = $r->url();
            $this->assertStringNotContainsString('TOKEN-X', $url);
            // Na API vai o token no cabeçalho; no download da capa (servidor de imagens) não vai token nenhum.
            $this->assertSame(str_contains($url, 'scontent.cdninstagram.com') ? [] : ['Bearer TOKEN-X'], $r->header('Authorization'));

            return match (true) {
                str_contains($url, 'scontent.cdninstagram.com') => Http::response(self::JPEG),
                str_contains($url, '/1784/media') => Http::response(['data' => [
                    ['id' => 'p1', 'media_type' => 'CAROUSEL_ALBUM', 'caption' => "Do CNPJ ao grau de risco\nlegenda", 'permalink' => 'https://www.instagram.com/p/AAA/', 'timestamp' => now()->subDays(2)->toIso8601String(), 'media_url' => 'https://scontent.cdninstagram.com/p1.jpg'],
                    ['id' => 'v1', 'media_type' => 'VIDEO', 'caption' => 'Vídeo', 'permalink' => 'https://www.instagram.com/reel/BBB/', 'timestamp' => now()->subDays(5)->toIso8601String(), 'thumbnail_url' => 'https://scontent.cdninstagram.com/v1.jpg'],
                    ['id' => 'velho', 'media_type' => 'CAROUSEL_ALBUM', 'caption' => 'Antigo', 'timestamp' => now()->subYear()->toIso8601String()],
                ]]),
                str_contains($url, '/1784/insights') => Http::response(['data' => [['name' => 'x', 'total_value' => ['value' => 42]]]]),
                str_contains($url, '/1784') => Http::response(['followers_count' => 82, 'media_count' => 30]),
                str_contains($url, 'p1/insights') => Http::response(['data' => [
                    ['name' => 'views', 'values' => [['value' => 223]]], ['name' => 'reach', 'values' => [['value' => 166]]],
                    ['name' => 'saved', 'values' => [['value' => 3]]], ['name' => 'follows', 'values' => [['value' => 1]]],
                ]]),
                str_contains($url, 'v1/insights') && str_contains($url, 'follows') => Http::response(['error' => ['message' => 'nao tem', 'code' => 100]], 400),
                str_contains($url, 'v1/insights') => Http::response(['data' => [['name' => 'views', 'values' => [['value' => 397]]]]]),
                str_contains($url, 'velho/insights') => Http::response(['error' => ['message' => 'Invalid parameter', 'code' => 100]], 400),
                str_contains($url, '/comments') => Http::response(['data' => [['id' => 'c1', 'text' => 'Quero o material', 'username' => 'fulano', 'timestamp' => now()->toIso8601String(), 'like_count' => 2]]]),
                default => Http::response(['error' => ['message' => 'inesperado '.$url]], 500),
            };
        });
    }

    public function test_importa_posts_capas_numeros_e_comentarios(): void
    {
        $this->fingirInstagram();
        Conteudo::create(['marca_id' => $this->marca->id, 'nome' => 'cnpj', 'titulo' => 'Do CNPJ ao grau de risco', 'estado' => 'publicado', 'link' => 'https://www.instagram.com/p/AAA/']);

        $this->artisan('central:coletar', ['o' => 'tudo', '--todos' => true])->assertSuccessful();

        $this->assertSame(3, Post::count());
        $p1 = Post::where('ig_id', 'p1')->first();
        $this->assertNotNull($p1->conteudo_id, 'post feito pela Central fica ligado ao carrossel');
        Storage::assertExists($p1->capa_caminho);
        $this->assertSame(223, $p1->ultimaMetrica->visualizacoes);
        $this->assertSame(1, $p1->ultimaMetrica->novos_seguidores);

        $v1 = Post::where('ig_id', 'v1')->first();
        $this->assertSame(397, $v1->ultimaMetrica->visualizacoes);
        $this->assertNull($v1->ultimaMetrica->novos_seguidores);

        $this->assertTrue(Post::where('ig_id', 'velho')->first()->sem_numeros);
        $this->assertSame('Quero o material', $p1->comentarios()->first()->texto);

        $dia = ContaDia::first();
        $this->assertSame(82, $dia->seguidores);
        $this->assertSame(42, $dia->alcance);
        $this->assertSame(now()->subDay()->toDateString(), $dia->dia->toDateString());
    }

    public function test_rodar_duas_vezes_no_mesmo_dia_atualiza_sem_duplicar(): void
    {
        $this->fingirInstagram();
        $this->artisan('central:coletar', ['o' => 'tudo', '--todos' => true])->assertSuccessful();
        $this->artisan('central:coletar', ['o' => 'tudo'])->assertSuccessful();

        $this->assertSame(3, Post::count());
        $this->assertSame(2, PostMetrica::count());
        $this->assertSame(1, ContaDia::count());
    }

    public function test_marca_sem_instagram_e_se_vazio_nao_chamam_a_api(): void
    {
        Http::fake();
        $this->marca->forceFill(['instagram_verificado_em' => null])->save();
        $this->artisan('central:coletar')->assertSuccessful();
        Http::assertNothingSent();

        $this->marca->forceFill(['instagram_verificado_em' => now()])->save();
        $ja = Post::create(['marca_id' => $this->marca->id, 'ig_id' => 'ja', 'tipo_midia' => 'IMAGE', 'publicado_em' => now()]);
        PostMetrica::create(['post_id' => $ja->id, 'dia' => today(), 'alcance' => 1]);
        $this->artisan('central:coletar', ['o' => 'tudo', '--todos' => true, '--se-vazio' => true])->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_token_vencido_no_meio_nao_marca_post_como_sem_numeros(): void
    {
        foreach (['a', 'b'] as $id) {
            Post::create(['marca_id' => $this->marca->id, 'ig_id' => $id, 'tipo_midia' => 'CAROUSEL_ALBUM', 'publicado_em' => now()->subDays(20)]);
        }

        Http::fake(fn (Request $r) => str_contains($r->url(), '/media')
            ? Http::response(['data' => []])
            : Http::response(['error' => ['message' => 'Error validating access token', 'code' => 190]], 400));
        $this->artisan('central:coletar', ['o' => 'posts', '--todos' => true])->assertFailed();

        $this->assertSame(0, Post::where('sem_numeros', true)->count());
        $this->assertStringContainsString('access token', $this->marca->fresh()->instagram_erro);
    }

    public function test_capa_so_dos_servidores_de_imagem_da_meta(): void
    {
        $api = app(\App\Services\Instagram\InstagramClient::class);
        Http::fake(['*' => Http::response(self::JPEG)]);
        $this->assertNull($api->baixar('https://exemplo-malicioso.com/capa.jpg'));
        $this->assertNull($api->baixar('http://scontent.cdninstagram.com/capa.jpg'));
        $this->assertNotNull($api->baixar('https://scontent-gru1-1.cdninstagram.com/capa.jpg'));
    }

    public function test_proxima_pagina_sem_token_na_url_e_so_do_host_da_api(): void
    {
        Http::fake(function (Request $r) {
            $this->assertStringNotContainsString('access_token', $r->url());

            return str_contains($r->url(), 'after=')
                ? Http::response(['data' => [['id' => 'b']]])
                : Http::response(['data' => [['id' => 'a']], 'paging' => ['next' => 'https://graph.facebook.com/v23.0/1784/media?access_token=TOKEN-X&after=XYZ']]);
        });
        $api = app(\App\Services\Instagram\InstagramClient::class);
        $this->assertCount(2, $api->todos('TOKEN-X', '1784/media'));
    }

    public function test_proxima_pagina_de_outro_host_e_recusada(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'paging' => ['next' => 'https://outro-host.com/roubar']])]);
        $this->expectException(\App\Services\Instagram\InstagramErro::class);
        app(\App\Services\Instagram\InstagramClient::class)->todos('TOKEN-X', '1784/media');
    }

    public function test_erro_do_instagram_fica_na_marca_sem_o_token(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Error validating access token TOKEN-X']], 400)]);
        $this->artisan('central:coletar', ['o' => 'conta'])->assertFailed();

        $erro = $this->marca->fresh()->instagram_erro;
        $this->assertStringContainsString('Error validating', $erro);
        $this->assertStringNotContainsString('TOKEN-X', $erro);
    }
}
