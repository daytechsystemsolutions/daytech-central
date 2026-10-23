<?php

namespace Tests\Feature;

use App\Models\CalendarioItem;
use App\Models\Marca;
use App\Models\Post;
use App\Models\TokenFabrica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotaAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('central:preparar');
        $this->marca = Marca::where('slug', 'mednext')->first();
        $this->token = str_repeat('c', 64);
        TokenFabrica::create(['nome' => 't', 'hash' => TokenFabrica::hashDe($this->token)]);
    }

    private function api(string $metodo, string $uri, array $dados = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->json($metodo, '/api/fabrica/marcas/mednext'.$uri, $dados);
    }

    private function usuario(): User
    {
        return User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);
    }

    public function test_classificar_e_a_tela_mostra_por_pilar(): void
    {
        $post = Post::create(['marca_id' => $this->marca->id, 'ig_id' => 'p1', 'tipo_midia' => 'CAROUSEL_ALBUM', 'publicado_em' => now()->subDays(20), 'nota' => 80, 'legenda' => 'Psicossocial']);
        $this->api('GET', '/posts/para-classificar')->assertJsonCount(1)->assertJsonFragment(['ig_id' => 'p1']);

        $this->api('PUT', '/posts/p1/como-foi-feito', ['pilar' => 'inventado', 'publico_principal' => 'consultoria', 'formato' => 'pergunta', 'chamada' => 'nenhuma', 'tema' => 'x', 'estimado' => true])->assertStatus(422);
        $this->api('PUT', '/posts/p1/como-foi-feito', ['pilar' => 'facilitar', 'publico_principal' => 'consultoria', 'formato' => 'pergunta', 'chamada' => 'nenhuma', 'tema' => 'psicossocial', 'estimado' => true])->assertOk();

        $this->assertTrue($post->fresh()->como_foi_feito_estimado);
        $this->api('GET', '/posts/para-classificar')->assertJsonCount(0);
        $this->actingAs($this->usuario())->get('/resultados/aprendemos')->assertSee('Por pilar')->assertSee('Facilitar');
        $this->actingAs($this->usuario())->get("/resultados/{$post->id}")->assertSee('Facilitar')->assertSee('estimado pela IA');
    }

    public function test_leitura_so_para_nota_fechada_e_aparece_no_post(): void
    {
        $aberto = Post::create(['marca_id' => $this->marca->id, 'ig_id' => 'aberto', 'tipo_midia' => 'VIDEO', 'publicado_em' => now()->subDay(), 'nota' => 50]);
        $fechado = Post::create(['marca_id' => $this->marca->id, 'ig_id' => 'fechado', 'tipo_midia' => 'VIDEO', 'publicado_em' => now()->subDays(9), 'nota' => 70, 'nota_fechada_em' => now()]);

        $this->api('GET', '/posts/para-ler')->assertJsonCount(1)->assertJsonFragment(['ig_id' => 'fechado']);
        $this->api('PUT', '/posts/fechado/leitura', ['texto' => 'curto'])->assertStatus(422);
        $this->api('PUT', '/posts/fechado/leitura', ['texto' => 'Muito compartilhado: a pergunta específica pegou uma dor real.'])->assertOk();

        $this->api('GET', '/posts/para-ler')->assertJsonCount(0);
        $this->actingAs($this->usuario())->get("/resultados/{$fechado->id}")->assertSee('pegou uma dor real');
        $this->assertNull($aberto->fresh()->leitura);
    }

    public function test_regras_trocam_por_semana_e_mantem_o_desligado_do_usuario(): void
    {
        $regras = ['regras' => [
            ['chave' => 'psicossocial', 'tipo' => 'faca', 'texto' => 'Manter psicossocial', 'evidencia' => '3 posts, nota 85'],
            ['chave' => 'sem-ano-novo', 'tipo' => 'evite', 'texto' => 'Evitar data comemorativa', 'evidencia' => 'nota 9'],
        ]];
        $this->api('PUT', '/regras', $regras)->assertOk();
        $regra = $this->marca->regras()->where('chave', 'psicossocial')->first();

        $usuario = $this->usuario();
        $this->actingAs($usuario)->post("/regras/{$regra->id}/alternar")->assertSessionHas('ok');
        $this->assertFalse($regra->fresh()->ativa);

        // Semana seguinte: a regra continua (mesma chave) e fica desligada; a outra sai da lista.
        $this->api('PUT', '/regras', ['regras' => [['chave' => 'psicossocial', 'tipo' => 'faca', 'texto' => 'Manter psicossocial (texto novo)']]])->assertOk();
        $this->assertFalse($regra->fresh()->ativa);
        $this->assertSame('Manter psicossocial (texto novo)', $regra->fresh()->texto);
        $this->assertFalse($this->marca->regras()->where('chave', 'sem-ano-novo')->first()->vigente);

        $this->api('GET', '/aprendizados')->assertOk()->assertJsonCount(1, 'regras')->assertJsonFragment(['chave' => 'psicossocial', 'ativa' => false]);
        $this->actingAs($usuario)->get('/resultados/aprendemos')->assertSee('texto novo')->assertDontSee('Evitar data comemorativa');
        $this->api('PUT', '/regras', ['regras' => [['chave' => 'Chave Ruim!', 'tipo' => 'faca', 'texto' => 'x']]])->assertStatus(422);
    }

    public function test_calendario_mostra_sugestao_da_ia_e_teste(): void
    {
        $this->api('PUT', '/calendario', ['itens' => [
            ['id' => '2099-11-03', 'data' => '2099-11-03', 'titulo' => 'Acessibilidade no psicossocial', 'estado' => 'tema_aprovado', 'sugerido_ia' => true, 'teste' => true, 'motivo' => 'pilar Facilitar tem nota 85'],
        ]])->assertOk();

        $this->assertTrue(CalendarioItem::first()->sugerido_ia);
        $this->actingAs($this->usuario())->get('/calendario?mes=2099-11')
            ->assertSee('sugerido pela IA')->assertSee('teste')->assertSee('pilar Facilitar tem nota 85');
    }

    public function test_api_da_rota_exige_a_chave_e_nao_mexe_em_post_de_outra_marca(): void
    {
        $this->getJson('/api/fabrica/marcas/mednext/aprendizados')->assertUnauthorized();
        $lupe = Marca::where('slug', 'lupe')->first();
        Post::create(['marca_id' => $lupe->id, 'ig_id' => 'lupe1', 'tipo_midia' => 'IMAGE', 'publicado_em' => now()]);
        $this->api('PUT', '/posts/lupe1/leitura', ['texto' => str_repeat('a', 30)])->assertNotFound();
    }
}
