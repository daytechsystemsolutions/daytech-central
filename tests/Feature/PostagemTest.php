<?php

namespace Tests\Feature;

use App\Models\Conteudo;
use App\Models\Marca;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PostagemTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG = "\xFF\xD8\xFF\xE0lamina";

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->artisan('central:preparar');
        $this->marca = Marca::where('slug', 'mednext')->first();
        $this->marca->forceFill(['instagram_conta_id' => '1784', 'instagram_token' => 'TOKEN-X', 'instagram_verificado_em' => now(), 'postagem_automatica' => true, 'postagem_ligada_em' => now()->subDay()])->save();
    }

    private function aprovado(array $extra = []): Conteudo
    {
        $c = Conteudo::create(array_merge([
            'marca_id' => $this->marca->id, 'nome' => 'ghe', 'titulo' => 'GHE', 'legenda_instagram' => 'Legenda do GHE',
            'versao' => 2, 'estado' => 'agendado', 'decisao' => 'aprovar', 'versao_decidida' => 2,
            'agendado_para' => now()->subMinute(), 'hash_laminas' => Conteudo::hashDasLaminas([self::JPEG, self::JPEG]),
        ], $extra));
        foreach ([1, 2] as $n) {
            Storage::put("conteudos/mednext/ghe/v2/0{$n}.jpg", self::JPEG);
            $c->laminas()->create(['versao' => 2, 'ordem' => $n, 'caminho' => "conteudos/mednext/ghe/v2/0{$n}.jpg"]);
        }

        return $c;
    }

    private function fingirInstagram(array &$chamadas): void
    {
        Http::fake(function (Request $r) use (&$chamadas) {
            $chamadas[] = $r->method().' '.$r->url().' '.$r->body();
            $this->assertSame(['Bearer TOKEN-X'], $r->header('Authorization'));

            return match (true) {
                str_contains($r->url(), 'media_publish') => Http::response(['id' => 'POST123']),
                $r->method() === 'POST' && str_contains($r->body(), 'CAROUSEL') => Http::response(['id' => 'CAR1']),
                $r->method() === 'POST' => Http::response(['id' => 'FILHO'.count($chamadas)]),
                str_contains($r->url(), 'POST123') => Http::response(['permalink' => 'https://www.instagram.com/p/NOVO/', 'timestamp' => now()->toIso8601String()]),
                default => Http::response(['status_code' => 'FINISHED']),
            };
        });
    }

    public function test_posta_o_carrossel_aprovado_com_links_assinados_e_registra(): void
    {
        $chamadas = [];
        $this->fingirInstagram($chamadas);
        $c = $this->aprovado();

        $this->artisan('central:postar')->assertSuccessful();

        $c->refresh();
        $this->assertSame('publicado', $c->estado);
        $this->assertSame('https://www.instagram.com/p/NOVO/', $c->link);
        $this->assertNotNull(Post::where('ig_id', 'POST123')->where('conteudo_id', $c->id)->first());
        $this->assertStringContainsString('signature', urldecode($chamadas[0]), 'imagem vai por link assinado');
        $this->assertStringContainsString('caption=Legenda', implode("\n", $chamadas));

        // Uma vez só.
        $this->artisan('central:postar')->assertSuccessful();
        $this->assertSame(1, collect($chamadas)->filter(fn ($x) => str_contains($x, 'media_publish'))->count());
    }

    public function test_nao_posta_sem_ligar_sem_aprovar_versao_certa_ou_antes_da_hora(): void
    {
        Http::fake();
        $this->marca->forceFill(['postagem_automatica' => false])->save();
        $this->aprovado();
        $this->artisan('central:postar');
        Http::assertNothingSent();

        $this->marca->forceFill(['postagem_automatica' => true])->save();
        Conteudo::query()->update(['versao_decidida' => 1]);
        $this->artisan('central:postar');
        Conteudo::query()->update(['versao_decidida' => 2, 'agendado_para' => now()->addHour()]);
        $this->artisan('central:postar');
        Conteudo::query()->update(['agendado_para' => now()->subMinute(), 'estado' => 'aguardando', 'decisao' => null]);
        $this->artisan('central:postar');
        Http::assertNothingSent();
    }

    public function test_lamina_trocada_depois_da_aprovacao_nao_posta(): void
    {
        Http::fake();
        $c = $this->aprovado();
        Storage::put('conteudos/mednext/ghe/v2/01.jpg', "\xFF\xD8\xFF\xE0OUTRA");

        $this->artisan('central:postar');

        Http::assertNothingSent();
        $this->assertSame('falhou', $c->fresh()->estado);
        $this->assertStringContainsString('não batem', $c->fresh()->erro_postagem);

        $usuario = User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);
        $this->actingAs($usuario)->get("/aprovar/{$c->id}")->assertSee('Não foi publicado')->assertSee('Tentar de novo');
        $this->actingAs($usuario)->post("/aprovar/{$c->id}/tentar-de-novo")->assertSessionHas('ok');
        $this->assertSame('agendado', $c->fresh()->estado);
    }

    public function test_link_publico_da_lamina_so_assinado_e_de_carrossel_agendado(): void
    {
        $c = $this->aprovado();
        $l = $c->laminasAtuais()->first();

        $this->get("/p/{$c->id}/{$l->id}")->assertForbidden();
        $assinado = URL::temporarySignedRoute('publico.lamina', now()->addHours(2), ['conteudo' => $c->id, 'lamina' => $l->id]);
        $this->get($assinado)->assertOk();

        $c->forceFill(['estado' => 'aguardando'])->save();
        $this->get($assinado)->assertNotFound();

        $this->travel(3)->hours();
        $c->forceFill(['estado' => 'agendado'])->save();
        $this->get($assinado)->assertForbidden();
    }

    public function test_ligar_postagem_exige_instagram_conectado(): void
    {
        $usuario = User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);
        $lupe = Marca::where('slug', 'lupe')->first();
        $this->actingAs($usuario)->post('/marcas/lupe/postagem', ['ligar' => 1])->assertSessionHas('aviso');
        $this->assertFalse($lupe->fresh()->postagem_automatica);

        $this->actingAs($usuario)->post('/marcas/mednext/postagem', ['ligar' => 0])->assertSessionHas('ok');
        $this->assertFalse($this->marca->fresh()->postagem_automatica);
        $this->actingAs($usuario)->post('/marcas/mednext/postagem', ['ligar' => 1]);
        $this->assertNotNull($this->marca->fresh()->postagem_ligada_em);
    }

    public function test_publicou_e_o_link_falhou_continua_publicado_e_nao_posta_de_novo(): void
    {
        $chamadas = [];
        Http::fake(function (Request $r) use (&$chamadas) {
            $chamadas[] = $r->url();

            return match (true) {
                str_contains($r->url(), 'media_publish') => Http::response(['id' => 'POST9']),
                $r->method() === 'POST' => Http::response(['id' => 'C'.count($chamadas)]),
                str_contains($r->url(), 'POST9') => Http::response(['error' => ['message' => 'fora do ar']], 500),
                default => Http::response(['status_code' => 'FINISHED']),
            };
        });
        $c = $this->aprovado();

        $this->artisan('central:postar')->assertSuccessful();

        $this->assertSame('publicado', $c->fresh()->estado);
        $this->assertNotNull(Post::where('ig_id', 'POST9')->first());
        $this->artisan('central:postar');
        $this->assertSame(1, collect($chamadas)->filter(fn ($u) => str_contains($u, 'media_publish'))->count());
    }

    public function test_aprovado_com_horario_perdido_antes_de_ligar_nao_sai_de_surpresa(): void
    {
        Http::fake();
        $this->marca->forceFill(['postagem_ligada_em' => now()])->save();
        $c = $this->aprovado(['agendado_para' => now()->subDays(2)]);

        $this->artisan('central:postar');

        Http::assertNothingSent();
        $this->assertSame('falhou', $c->fresh()->estado);
        $this->assertStringContainsString('Tentar de novo', $c->fresh()->erro_postagem);
    }

    public function test_tirado_no_meio_nao_e_reservado(): void
    {
        Http::fake();
        $c = $this->aprovado();
        $c->forceFill(['estado' => 'tirado', 'decisao' => 'tirar'])->save();
        $this->assertNull(app(\App\Services\Postagem\PostagemInstagram::class)->postar($c));
        Http::assertNothingSent();
    }

    public function test_legenda_acima_do_limite_nao_cria_conteineres(): void
    {
        Http::fake();
        $c = $this->aprovado(['legenda_instagram' => str_repeat('#tag ', 31)]);
        $this->artisan('central:postar');
        Http::assertNothingSent();
        $this->assertSame('falhou', $c->fresh()->estado);
    }
}
