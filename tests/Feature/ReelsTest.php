<?php

namespace Tests\Feature;

use App\Models\Conteudo;
use App\Models\Marca;
use App\Models\Post;
use App\Models\PostMetrica;
use App\Models\TokenFabrica;
use App\Models\User;
use App\Services\Resultados\NotaDoPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReelsTest extends TestCase
{
    use RefreshDatabase;

    private const MP4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isomvideo-de-teste";

    private const JPEG = "\xFF\xD8\xFF\xE0capa";

    private Marca $marca;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->artisan('central:preparar');
        $this->marca = Marca::where('slug', 'mednext')->first();
        $this->token = str_repeat('e', 64);
        TokenFabrica::create(['nome' => 't', 'hash' => TokenFabrica::hashDe($this->token)]);
    }

    private function api(string $metodo, string $uri, array $dados = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->json($metodo, '/api/fabrica/marcas/mednext'.$uri, $dados);
    }

    private function enviarVideo(int $versao, string $bytes = self::MP4, ?string $sha = null)
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json'])
            ->post('/api/fabrica/marcas/mednext/conteudos/reels-psicossocial/video', [
                'versao' => $versao, 'sha256' => $sha ?? hash('sha256', $bytes), 'duracao_segundos' => 42,
                'video' => UploadedFile::fake()->createWithContent('reels.mp4', $bytes),
                'capa' => UploadedFile::fake()->createWithContent('capa.jpg', self::JPEG),
            ]);
    }

    private function roteiro(array $extra = []): array
    {
        return array_merge([
            'nome' => 'reels-psicossocial', 'titulo' => 'Formulário psicossocial pelo celular', 'tipo' => 'reels', 'versao' => 1,
            'data' => now()->addDays(3)->toDateString(), 'hora' => '11:40', 'instagram' => 'Legenda do Reels', 'duracao_segundos' => 40,
            'roteiro' => [
                ['fala' => 'Aplicar o formulário psicossocial ainda é no papel?', 'mostra' => 'Pergunta grande na tela'],
                ['fala' => 'Manda o link e cada um responde no celular.', 'mostra' => 'Tela do formulário no celular (demonstração)'],
            ],
        ], $extra);
    }

    private function usuario(): User
    {
        return User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);
    }

    public function test_duas_aprovacoes_roteiro_e_depois_o_video(): void
    {
        $u = $this->usuario();
        $this->api('POST', '/conteudos', $this->roteiro(['roteiro' => []]))->assertStatus(422);
        $this->api('POST', '/conteudos', $this->roteiro())->assertOk();
        $c = Conteudo::first();
        $this->assertSame(['reels', 'roteiro', 'aguardando'], [$c->tipo, $c->fase, $c->estado]);

        $this->actingAs($u)->get("/aprovar/{$c->id}")->assertSee('Aprovar roteiro')->assertSee('Na tela:')->assertDontSee('Aprovar e agendar');
        // "Aprovar e agendar" não vale para roteiro.
        $this->actingAs($u)->post("/aprovar/{$c->id}/aprovar", ['versao' => 1]);
        $this->assertSame('aguardando', $c->fresh()->estado);

        // Vídeo antes do roteiro aprovado: recusado.
        $this->enviarVideo(2)->assertStatus(409);

        $this->actingAs($u)->post("/aprovar/{$c->id}/roteiro", ['versao' => 1])->assertSessionHas('ok');
        $c->refresh();
        $this->assertSame(['roteiro_aprovado', 1], [$c->estado, $c->roteiro_aprovado_versao]);
        $this->api('GET', '/conteudos/decisoes')->assertJsonFragment(['decisao' => 'aprovar_roteiro', 'fase' => 'roteiro', 'roteiro_aprovado_versao' => 1]);

        // Vídeo: mesma versão do roteiro não; impressão digital errada não; não-MP4 não.
        $this->enviarVideo(1)->assertStatus(409);
        $this->enviarVideo(2, self::MP4, str_repeat('0', 64))->assertStatus(422);
        $this->enviarVideo(2, 'nao e video nenhum mesmo')->assertStatus(422);
        $this->enviarVideo(2)->assertOk()->assertJsonFragment(['estado' => 'aguardando', 'video_hash' => hash('sha256', self::MP4)]);

        $c->refresh();
        $this->assertSame(['video', 2, 42], [$c->fase, $c->versao, $c->duracao_segundos]);
        $this->actingAs($u)->get("/aprovar/{$c->id}")->assertSee('<video', false)->assertSee('Aprovar e agendar')->assertSee('name="midia" value="'.hash('sha256', self::MP4).'"', false)->assertSee('Ver o roteiro aprovado');
        $this->actingAs($u)->get("/aprovar/{$c->id}/video.mp4")->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->actingAs($u)->get('/aprovar')->assertSee('Reels');

        $this->actingAs($u)->post("/aprovar/{$c->id}/aprovar", ['versao' => 2, 'midia' => $c->fresh()->video_hash])->assertSessionHas('ok');
        $this->assertSame(['agendado', 'aprovar', 2], [$c->fresh()->estado, $c->fresh()->decisao, $c->fresh()->versao_decidida]);

        // Já decidido: a mesma versão do vídeo não troca.
        $this->enviarVideo(2)->assertStatus(409);
    }

    public function test_video_trocado_na_mesma_versao_nao_aprova_o_que_nao_foi_visto(): void
    {
        $u = $this->usuario();
        $this->api('POST', '/conteudos', $this->roteiro());
        $c = Conteudo::first();
        $this->actingAs($u)->post("/aprovar/{$c->id}/roteiro", ['versao' => 1]);
        $this->enviarVideo(2)->assertOk();
        $visto = $c->fresh()->video_hash; // o que estava na tela

        $this->enviarVideo(2, self::MP4.'re-render')->assertOk(); // o kit reenvia a mesma versão, sem decisão
        $this->actingAs($u)->post("/aprovar/{$c->id}/aprovar", ['versao' => 2, 'midia' => $visto])->assertSessionHas('aviso');
        $this->assertSame('aguardando', $c->fresh()->estado);
        $this->assertStringContainsString('Reels', session('aviso'));

        $this->actingAs($u)->post("/aprovar/{$c->id}/aprovar", ['versao' => 2, 'midia' => $c->fresh()->video_hash])->assertSessionHas('ok');
        $this->assertSame('agendado', $c->fresh()->estado);
    }

    public function test_tirado_e_desfeito_nao_aceitam_video(): void
    {
        $u = $this->usuario();
        $this->api('POST', '/conteudos', $this->roteiro());
        $c = Conteudo::first();

        $this->actingAs($u)->post("/aprovar/{$c->id}/roteiro", ['versao' => 1]);
        $this->actingAs($u)->post("/aprovar/{$c->id}/desfazer")->assertSessionHas('ok');
        $this->assertNull($c->fresh()->roteiro_aprovado_versao);
        $this->enviarVideo(2)->assertStatus(409);

        $this->actingAs($u)->post("/aprovar/{$c->id}/roteiro", ['versao' => 1]);
        $c->forceFill(['agendado_para' => now()->subDay()])->save();
        $this->assertTrue($c->fresh()->podeDesfazer(), 'roteiro aprovado se desfaz mesmo com a data do calendário passada');

        $this->actingAs($u)->post("/aprovar/{$c->id}/tirar")->assertSessionHas('ok');
        $this->assertNull($c->fresh()->roteiro_aprovado_versao);
        $this->enviarVideo(2)->assertStatus(409);
        $this->assertSame('tirado', $c->fresh()->estado);
    }

    public function test_envio_durante_a_postagem_e_arquivo_que_nao_e_mp4(): void
    {
        $u = $this->usuario();
        $this->api('POST', '/conteudos', $this->roteiro());
        $c = Conteudo::first();
        $this->actingAs($u)->post("/aprovar/{$c->id}/roteiro", ['versao' => 1]);

        // ftyp de imagem (HEIC) não passa como vídeo.
        $this->enviarVideo(2, "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00imagem")->assertStatus(422);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json'])
            ->post('/api/fabrica/marcas/mednext/conteudos/reels-psicossocial/video', [
                'versao' => 2, 'sha256' => str_repeat('a', 64),
                'video' => UploadedFile::fake()->create('grande.mp4', 96 * 1024, 'video/mp4'),
            ])->assertStatus(422);

        $c->forceFill(['estado' => 'postando'])->save();
        $this->api('POST', '/conteudos', $this->roteiro(['versao' => 3]))->assertStatus(409);
        $this->enviarVideo(3)->assertStatus(409);
        $c->forceFill(['estado' => 'publicado'])->save();
        $this->api('POST', '/conteudos', $this->roteiro(['versao' => 3]))->assertStatus(409);
    }

    public function test_ajuste_no_roteiro_bloqueia_o_video_antigo_e_roteiro_novo_zera(): void
    {
        $u = $this->usuario();
        $this->api('POST', '/conteudos', $this->roteiro());
        $c = Conteudo::first();
        $this->actingAs($u)->post("/aprovar/{$c->id}/roteiro", ['versao' => 1]);
        $this->actingAs($u)->post("/aprovar/{$c->id}/ajuste", ['nota' => 'cena 2 mais curta'])->assertSessionHas('ok');
        $this->assertNull($c->fresh()->roteiro_aprovado_versao);
        $this->enviarVideo(2)->assertStatus(409);

        $this->api('POST', '/conteudos', $this->roteiro(['versao' => 2, 'ajuste_feito' => 'cena 2 mais curta']))->assertOk();
        $this->assertSame(['aguardando', 'roteiro'], [$c->fresh()->estado, $c->fresh()->fase]);

        // Carrossel com o mesmo nome não vira Reels (nem o contrário).
        $this->api('POST', '/conteudos', $this->roteiro(['tipo' => 'carrossel', 'versao' => 3, 'laminas' => [base64_encode(self::JPEG)]]))->assertStatus(409);
    }

    public function test_posta_o_reels_aprovado_pela_vps(): void
    {
        $this->marca->forceFill(['instagram_conta_id' => '1784', 'instagram_token' => 'TOKEN-X', 'instagram_verificado_em' => now(), 'postagem_automatica' => true, 'postagem_ligada_em' => now()->subDay()])->save();
        Storage::put('conteudos/mednext/reels/v2/reels.mp4', self::MP4);
        Storage::put('conteudos/mednext/reels/v2/capa.jpg', self::JPEG);
        $c = Conteudo::create(['marca_id' => $this->marca->id, 'nome' => 'reels', 'titulo' => 'Reels', 'tipo' => 'reels', 'fase' => 'video',
            'legenda_instagram' => 'Legenda do Reels', 'versao' => 2, 'estado' => 'agendado', 'decisao' => 'aprovar', 'versao_decidida' => 2,
            'roteiro_aprovado_versao' => 1, 'agendado_para' => now()->subMinute(), 'video_caminho' => 'conteudos/mednext/reels/v2/reels.mp4',
            'capa_caminho' => 'conteudos/mednext/reels/v2/capa.jpg', 'video_hash' => hash('sha256', self::MP4)]);

        $chamadas = [];
        Http::fake(function (Request $r) use (&$chamadas) {
            $chamadas[] = $r->method().' '.$r->url().' '.urldecode($r->body());

            return match (true) {
                str_contains($r->url(), 'media_publish') => Http::response(['id' => 'REEL123']),
                $r->method() === 'POST' => Http::response(['id' => 'CONT1']),
                str_contains($r->url(), 'REEL123') => Http::response(['permalink' => 'https://www.instagram.com/reel/NOVO/', 'timestamp' => now()->toIso8601String()]),
                default => Http::response(['status_code' => 'FINISHED']),
            };
        });

        $this->artisan('central:postar')->assertSuccessful();

        $this->assertSame('publicado', $c->fresh()->estado);
        $this->assertStringContainsString('media_type=REELS', $chamadas[0]);
        $this->assertStringContainsString('share_to_feed=true', $chamadas[0]);
        $this->assertStringContainsString('/pv/'.$c->id, $chamadas[0]);
        $this->assertStringContainsString('cover_url=', $chamadas[0]);
        $this->assertSame('VIDEO', Post::where('ig_id', 'REEL123')->value('tipo_midia'));

        // O link público do vídeo: só assinado, e só depois de aprovado (postando/publicado).
        $assinado = URL::temporarySignedRoute('publico.video', now()->addHours(2), ['conteudo' => $c->id]);
        $this->get("/pv/{$c->id}")->assertForbidden();
        $this->get($assinado)->assertOk();
        $c->forceFill(['estado' => 'aguardando'])->save();
        $this->get($assinado)->assertNotFound();
    }

    public function test_video_trocado_no_disco_nao_sai(): void
    {
        $this->marca->forceFill(['instagram_conta_id' => '1784', 'instagram_token' => 'TOKEN-X', 'instagram_verificado_em' => now(), 'postagem_automatica' => true, 'postagem_ligada_em' => now()->subDay()])->save();
        Storage::put('conteudos/mednext/reels/v2/reels.mp4', self::MP4.'outro');
        $c = Conteudo::create(['marca_id' => $this->marca->id, 'nome' => 'reels', 'titulo' => 'Reels', 'tipo' => 'reels', 'fase' => 'video',
            'versao' => 2, 'estado' => 'agendado', 'decisao' => 'aprovar', 'versao_decidida' => 2, 'agendado_para' => now()->subMinute(),
            'video_caminho' => 'conteudos/mednext/reels/v2/reels.mp4', 'video_hash' => hash('sha256', self::MP4)]);
        Http::fake();

        $this->artisan('central:postar');

        Http::assertNothingSent();
        $this->assertSame('falhou', $c->fresh()->estado);
        $this->assertStringContainsString('não bate', $c->fresh()->erro_postagem);
    }

    public function test_lamina_publica_abre_enquanto_posta(): void
    {
        Storage::put('conteudos/mednext/c/v1/01.jpg', self::JPEG);
        $c = Conteudo::create(['marca_id' => $this->marca->id, 'nome' => 'c', 'titulo' => 'C', 'versao' => 1, 'estado' => 'postando']);
        $l = $c->laminas()->create(['versao' => 1, 'ordem' => 1, 'caminho' => 'conteudos/mednext/c/v1/01.jpg']);

        $this->get(URL::temporarySignedRoute('publico.lamina', now()->addHours(2), ['conteudo' => $c->id, 'lamina' => $l->id]))->assertOk();
    }

    public function test_retencao_entra_na_nota_so_do_reels(): void
    {
        $outro = Post::create(['marca_id' => $this->marca->id, 'ig_id' => 'r0', 'tipo_midia' => 'VIDEO', 'publicado_em' => now()->subDays(20)]);
        PostMetrica::create(['post_id' => $outro->id, 'dia' => now()->subDays(12)->toDateString(), 'alcance' => 100, 'salvos' => 2, 'compartilhamentos' => 2, 'tempo_medio_ms' => 5000]);
        $reels = Post::create(['marca_id' => $this->marca->id, 'ig_id' => 'r1', 'tipo_midia' => 'VIDEO', 'publicado_em' => now()->subDays(8)]);
        PostMetrica::create(['post_id' => $reels->id, 'dia' => now()->toDateString(), 'alcance' => 100, 'salvos' => 2, 'compartilhamentos' => 2, 'tempo_medio_ms' => 10000]);

        (new NotaDoPost)->atualizar($this->marca);

        $partes = $reels->fresh()->nota_partes['partes'];
        $this->assertSame(100, $partes['retencao']['pontos'], 'o dobro do tempo médio dos outros Reels');
        $this->assertSame(10.0, (float) $partes['retencao']['valor']);
        $this->assertArrayNotHasKey('crescimento', $partes);
    }

    public function test_ritmo_da_marca(): void
    {
        $this->actingAs($this->usuario())->post("/marcas/{$this->marca->slug}/ritmo", ['carrosseis_semana' => 3, 'reels_semana' => 2])->assertSessionHas('ok');
        $this->assertSame([3, 2], [$this->marca->fresh()->carrosseis_semana, $this->marca->fresh()->reels_semana]);
        $this->api('GET', '/pesquisa/insumos')->assertJsonFragment(['carrosseis_semana' => 3, 'reels_semana' => 2]);
    }
}
