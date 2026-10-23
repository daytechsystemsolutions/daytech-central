<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\Pauta;
use App\Models\TokenFabrica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PautaTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('central:preparar');
        $this->marca = Marca::where('slug', 'mednext')->first();
        $this->token = str_repeat('d', 64);
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

    private function pauta(array $extra = []): array
    {
        return array_merge([
            'chave' => 'esocial-s2240-prazo', 'titulo' => 'S-2240: o prazo que ninguém lembra', 'ideia' => 'Quando enviar e o que trava.',
            'pilar' => 'ensinar', 'publico' => 'consultoria', 'formato' => 'carrossel',
            'partes' => [
                'mercado' => ['pontos' => 80, 'resumo' => 'Muitas buscas sobre o prazo'],
                'historico' => ['pontos' => 60, 'resumo' => 'Posts de eSocial ficaram na média'],
                'prazo' => ['pontos' => 100, 'resumo' => 'Vence dia 15'],
                'duvidas' => ['pontos' => 40, 'resumo' => '2 comentários perguntando'],
            ],
            'fontes' => [['titulo' => 'Manual do eSocial (gov.br)', 'url' => 'https://www.gov.br/esocial/pt-br']],
        ], $extra);
    }

    public function test_potencial_usa_os_pesos_do_plano(): void
    {
        // 80*0,3 + 60*0,3 + 100*0,2 + 40*0,2 = 24 + 18 + 20 + 8 = 70
        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta()]])->assertOk();
        $this->assertSame(70, Pauta::first()->potencial);

        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta(['partes' => ['mercado' => ['pontos' => 120, 'resumo' => 'x']]])]])->assertStatus(422);
        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta(['fontes' => [['titulo' => 'x', 'url' => 'nao-e-url']]])]])->assertStatus(422);
        $this->withHeaders(['Authorization' => 'Bearer errado'])->json('PUT', '/api/fabrica/marcas/mednext/pautas', ['pautas' => [$this->pauta()]])->assertStatus(401);
    }

    public function test_reenviar_atualiza_sem_desfazer_a_decisao_do_usuario(): void
    {
        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta()]]);
        Pauta::first()->forceFill(['estado' => 'descartada'])->save();

        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta(['titulo' => 'Título novo'])]])->assertOk();
        $p = Pauta::first();
        $this->assertSame(1, Pauta::count());
        $this->assertSame('descartada', $p->estado);
        $this->assertSame('Título novo', $p->titulo);
    }

    public function test_por_no_calendario_e_o_porque_aparece_no_tema(): void
    {
        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta()]]);
        $pauta = Pauta::first();
        $u = $this->usuario();

        $this->actingAs($u)->get('/calendario')->assertSee('Banco de pautas')->assertSee('S-2240: o prazo que ninguém lembra')->assertSee('Manual do eSocial (gov.br)');

        $dia = now()->addDays(10)->toDateString();
        $this->actingAs($u)->post("/pautas/{$pauta->id}/calendario", ['data' => $dia, 'hora' => '11:40'])->assertRedirect();
        $item = $this->marca->calendario()->where('pauta_chave', 'esocial-s2240-prazo')->first();
        $this->assertNotNull($item);
        $this->assertSame('central', $item->origem);
        $this->assertSame(['consultoria'], $item->publico);
        $this->assertSame('no_calendario', $pauta->fresh()->estado);

        // De novo não entra duas vezes.
        $this->actingAs($u)->post("/pautas/{$pauta->id}/calendario", ['data' => $dia]);
        $this->assertSame(1, $this->marca->calendario()->where('pauta_chave', 'esocial-s2240-prazo')->count());

        $this->actingAs($u)->get('/calendario?mes='.substr($dia, 0, 7))->assertSee('Por que este tema')->assertSee('potencial 70 de 100');
        $this->api('GET', '/calendario/da-central')->assertJsonFragment(['pauta' => 'esocial-s2240-prazo']);
        $this->api('GET', '/pesquisa/insumos')->assertOk()->assertJsonFragment(['chave' => 'esocial-s2240-prazo', 'estado' => 'no_calendario']);
    }

    public function test_tema_do_kit_ligado_a_pauta(): void
    {
        $this->api('PUT', '/calendario', ['itens' => [['id' => '2026-12-02', 'data' => now()->addDays(5)->toDateString(), 'hora' => '11:40', 'titulo' => 'Tema do kit', 'estado' => 'proposto']]])->assertOk();
        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta(['calendario_chave' => '2026-12-02'])]])->assertOk();
        $this->assertSame('esocial-s2240-prazo', $this->marca->calendario()->where('chave', '2026-12-02')->value('pauta_chave'));
        $this->assertSame('no_calendario', Pauta::first()->estado);
    }

    public function test_descartar_e_outra_marca_nao_mexe(): void
    {
        $this->api('PUT', '/pautas', ['pautas' => [$this->pauta()]]);
        $pauta = Pauta::first();
        $outra = Marca::create(['slug' => 'outra', 'nome' => 'Outra', 'iniciais' => 'OU', 'ativa' => true, 'ordem' => 99]);
        $alheia = Pauta::create(['marca_id' => $outra->id, 'chave' => 'x', 'titulo' => 'Alheia', 'formato' => 'carrossel', 'potencial' => 50, 'partes' => []]);
        $u = $this->usuario();

        $this->actingAs($u)->post("/pautas/{$alheia->id}/descartar")->assertNotFound();
        $this->actingAs($u)->post("/pautas/{$alheia->id}/calendario", ['data' => now()->addDay()->toDateString()])->assertNotFound();
        $this->actingAs($u)->post("/pautas/{$pauta->id}/descartar")->assertRedirect();
        $this->assertSame('descartada', $pauta->fresh()->estado);
        $this->actingAs($u)->get('/calendario')->assertDontSee('S-2240: o prazo que ninguém lembra');
    }
}
