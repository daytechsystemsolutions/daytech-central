<?php

namespace Tests\Feature;

use App\Models\CalendarioItem;
use App\Models\Conteudo;
use App\Models\Marca;
use App\Models\TokenFabrica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AprovacaoTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG = "\xFF\xD8\xFF\xE0fake-jpeg";

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->artisan('central:preparar');
        $this->token = str_repeat('a', 64);
        TokenFabrica::create(['nome' => 'teste', 'hash' => TokenFabrica::hashDe($this->token)]);
    }

    private function api(string $metodo, string $uri, array $dados = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->json($metodo, '/api/fabrica/marcas/mednext'.$uri, $dados);
    }

    private function usuario(): User
    {
        return User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);
    }

    private function carrossel(array $extra = []): array
    {
        return array_merge([
            'nome' => '2026-11-ghe', 'titulo' => 'GHE explicado', 'calendario_chave' => '2026-11-03',
            'publico' => ['consultoria'], 'instagram' => 'Legenda', 'linkedin' => 'Texto', 'versao' => 1,
            'laminas' => [base64_encode(self::JPEG), base64_encode(self::JPEG)], 'pdf' => base64_encode('%PDF-1.4 x'),
        ], $extra);
    }

    private function semearCalendario(): void
    {
        $this->api('PUT', '/calendario', ['itens' => [
            ['id' => '2026-11-03', 'data' => '2026-11-03', 'hora' => '11:40', 'titulo' => 'GHE explicado', 'estado' => 'tema_aprovado'],
            ['id' => '2026-11-30', 'data' => '2026-11-30', 'hora' => '11:40', 'titulo' => 'Último dia do mês', 'estado' => 'proposto'],
        ]])->assertOk()->assertJson(['itens' => 2]);
    }

    public function test_api_sem_chave_ou_com_chave_errada_recusa(): void
    {
        $this->getJson('/api/fabrica/marcas/mednext/ideias')->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('b', 64)])->getJson('/api/fabrica/marcas/mednext/ideias')->assertUnauthorized();
        $this->api('GET', '/ideias')->assertOk();
    }

    public function test_comando_cria_chave_e_so_guarda_o_hash(): void
    {
        $this->artisan('central:token-fabrica', ['nome' => 'kit-pc'])->expectsOutputToContain('CENTRAL_TOKEN=')->assertSuccessful();
        $this->assertSame(2, TokenFabrica::count());
        $this->assertSame(64, strlen(TokenFabrica::latest('id')->first()->hash));
    }

    public function test_carrossel_chega_aparece_e_aprovar_agenda_a_versao_vista(): void
    {
        $this->semearCalendario();
        $this->api('POST', '/conteudos', $this->carrossel())->assertOk()->assertJson(['estado' => 'aguardando', 'versao' => 1]);

        $conteudo = Conteudo::first();
        $this->assertSame('2026-11-03 11:40', $conteudo->agendado_para->format('Y-m-d H:i'));
        $this->assertCount(2, $conteudo->laminasAtuais());
        $usuario = $this->usuario();

        $this->actingAs($usuario)->get('/')->assertSee('conteúdo espera você');
        $this->actingAs($usuario)->get('/aprovar')->assertOk()->assertSee('1 carrossel espera você')->assertSee('Semana de 02/11 a 08/11');
        $this->actingAs($usuario)->get("/aprovar/{$conteudo->id}")->assertOk()->assertSee('Aprovar e agendar')->assertSee('consultorias');
        $lamina = $conteudo->laminasAtuais()->first();
        $this->actingAs($usuario)->get("/aprovar/{$conteudo->id}/laminas/{$lamina->id}")->assertOk();

        // Versão errada (o carrossel mudou enquanto a tela estava aberta): não aprova.
        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/aprovar", ['versao' => 2])->assertSessionHas('aviso');
        $this->assertSame('aguardando', $conteudo->fresh()->estado);

        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/aprovar", ['versao' => 1])->assertSessionHas('ok');
        $this->assertSame('agendado', $conteudo->fresh()->estado);

        $this->api('GET', '/conteudos/decisoes')->assertOk()->assertJsonFragment([
            'id' => '2026-11-ghe', 'decisao' => 'aprovar', 'versao_decidida' => 1, 'data' => '2026-11-03', 'hora' => '11:40',
            'hash_laminas' => Conteudo::hashDasLaminas([self::JPEG, self::JPEG]),
        ]);
    }

    public function test_desfazer_ajuste_e_versao_nova(): void
    {
        $this->semearCalendario();
        $this->api('POST', '/conteudos', $this->carrossel());
        $conteudo = Conteudo::first();
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/aprovar", ['versao' => 1]);
        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/desfazer")->assertSessionHas('ok');
        $this->api('GET', '/conteudos/decisoes')->assertJsonFragment(['id' => '2026-11-ghe', 'decisao' => 'aguardando']);

        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/ajuste", ['nota' => ''])->assertSessionHasErrors('nota');
        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/ajuste", ['nota' => 'Capa mais curta']);
        $this->assertSame('ajuste', $conteudo->fresh()->estado);

        // Já decidido: a mesma versão não aceita lâminas novas nem versão menor. Versão nova: volta a esperar.
        $this->api('POST', '/conteudos', $this->carrossel())->assertStatus(409);
        $this->api('POST', '/conteudos', $this->carrossel(['laminas' => [], 'pdf' => null]))->assertJson(['estado' => 'ajuste']);
        $this->api('POST', '/conteudos', $this->carrossel(['versao' => 2, 'ajuste_feito' => 'capa menor']))->assertJson(['estado' => 'aguardando', 'versao' => 2]);
        $this->assertNull($conteudo->fresh()->decisao);
        $this->actingAs($usuario)->get("/aprovar/{$conteudo->id}")->assertSee('capa menor');
        $this->api('POST', '/conteudos', $this->carrossel(['versao' => 1]))->assertStatus(409);
        $this->assertSame(2, $conteudo->fresh()->versao);
    }

    public function test_versao_nova_sem_laminas_e_arquivo_que_nao_e_jpeg_sao_recusados(): void
    {
        $this->api('POST', '/conteudos', $this->carrossel(['laminas' => []]))->assertStatus(422);
        $this->api('POST', '/conteudos', $this->carrossel(['laminas' => [base64_encode('<?php echo 1;')]]))->assertStatus(422);
        $this->api('POST', '/conteudos', $this->carrossel(['nome' => '../fora']))->assertStatus(422);
        $this->assertSame(0, Conteudo::count());
    }

    public function test_aprovar_sem_data_pede_quando(): void
    {
        $this->api('POST', '/conteudos', $this->carrossel(['calendario_chave' => null]));
        $conteudo = Conteudo::first();
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/aprovar", ['versao' => 1])->assertSessionHasErrors('quando');
        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/aprovar", ['versao' => 1, 'quando' => now()->addDays(3)->format('Y-m-d\TH:i')])->assertSessionHas('ok');
        $this->assertSame('agendado', $conteudo->fresh()->estado);
    }

    public function test_data_do_calendario_ja_passou_pede_horario_novo(): void
    {
        $this->api('PUT', '/calendario', ['itens' => [['id' => '2020-01-06', 'data' => '2020-01-06', 'titulo' => 'Antigo']]]);
        $this->api('POST', '/conteudos', $this->carrossel(['calendario_chave' => '2020-01-06']));
        $conteudo = Conteudo::first();
        $usuario = $this->usuario();

        $this->actingAs($usuario)->get("/aprovar/{$conteudo->id}")->assertSee('Quando postar?');
        $this->actingAs($usuario)->post("/aprovar/{$conteudo->id}/aprovar", ['versao' => 1])->assertSessionHasErrors('quando');
        $this->assertSame('aguardando', $conteudo->fresh()->estado);
    }

    public function test_publicado_vindo_do_kit(): void
    {
        $this->api('POST', '/conteudos', $this->carrossel(['publicado' => ['link' => 'https://www.instagram.com/p/abc/', 'em' => '2026-10-22 18:06']]))
            ->assertJson(['estado' => 'publicado']);
        $this->actingAs($this->usuario())->get('/publicados')->assertSee('GHE explicado')->assertSee('instagram.com/p/abc');
    }

    public function test_calendario_mostra_o_mes_inteiro_e_trocar_volta_para_o_kit(): void
    {
        $this->semearCalendario();
        $usuario = $this->usuario();

        $this->actingAs($usuario)->get('/calendario?mes=2026-11')->assertSee('GHE explicado')->assertSee('Último dia do mês');

        $item = CalendarioItem::where('chave', '2026-11-30')->first();
        $this->actingAs($usuario)->post("/calendario/{$item->id}/trocar", ['nota' => 'prazos do eSocial'])->assertRedirect('/calendario?mes=2026-11');
        $this->api('GET', '/calendario/decisoes')->assertJsonFragment(['id' => '2026-11-30', 'decisao' => 'trocar', 'nota' => 'prazos do eSocial']);

        // O kit reenviar o calendário não apaga a decisão do tema.
        $this->semearCalendario();
        $this->assertSame('trocar', $item->fresh()->decisao);

        // Produzido: não dá mais para trocar pelo calendário.
        $produzido = CalendarioItem::where('chave', '2026-11-03')->first();
        $produzido->update(['estado' => 'produzido']);
        $this->actingAs($usuario)->post("/calendario/{$produzido->id}/tirar")->assertSessionHas('aviso');
        $this->assertNull($produzido->fresh()->decisao);
    }

    public function test_ideias_por_marca(): void
    {
        $usuario = $this->usuario();
        $this->actingAs($usuario)->post('/ideias', ['texto' => 'Carrossel sobre NR-1'])->assertSessionHas('ok');
        $this->api('GET', '/ideias')->assertJsonFragment(['texto' => 'Carrossel sobre NR-1']);

        $ideia = Marca::where('slug', 'mednext')->first()->ideias()->first();
        $this->actingAs($usuario)->post('/marcas/lupe/abrir');
        $this->actingAs($usuario)->delete("/ideias/{$ideia->id}")->assertNotFound();
    }

    public function test_produzir_semana_pedido_vai_para_o_kit_e_acaba_quando_o_carrossel_chega(): void
    {
        $this->semearCalendario();
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post('/calendario/semana/2026-11-02/produzir')->assertSessionHas('ok');
        $this->api('GET', '/pedidos')->assertOk()->assertJsonCount(1)->assertJsonFragment(['id' => '2026-11-03', 'iniciado_em' => null]);
        $this->actingAs($usuario)->get('/calendario?mes=2026-11')->assertSee('produção pedida');

        $this->api('POST', '/pedidos/2026-11-03/iniciar')->assertOk();
        $this->actingAs($usuario)->get('/calendario?mes=2026-11')->assertSee('em produção');
        $item = CalendarioItem::where('chave', '2026-11-03')->first();
        $this->actingAs($usuario)->post("/calendario/{$item->id}/cancelar-producao")->assertSessionHas('aviso');

        $this->api('POST', '/conteudos', $this->carrossel());
        $this->api('GET', '/pedidos')->assertJsonCount(0);
        $this->assertSame('produzido', $item->fresh()->estado);
    }

    public function test_novo_tema_na_central_com_produzir_agora(): void
    {
        $usuario = $this->usuario();
        $dia = now()->addDays(3)->toDateString();

        $this->actingAs($usuario)->post('/calendario/temas', ['data' => now()->subDay()->toDateString(), 'titulo' => 'Ontem'])->assertSessionHasErrors('data');
        $this->actingAs($usuario)->post('/calendario/temas', ['data' => $dia, 'titulo' => '5 erros no PGR', 'publico' => ['consultoria'], 'produzir' => 1])
            ->assertSessionHas('ok');
        $this->actingAs($usuario)->post('/calendario/temas', ['data' => $dia, 'titulo' => 'Outro no mesmo dia']);

        $this->api('GET', '/calendario/da-central')->assertJsonCount(2)->assertJsonFragment(['id' => $dia, 'titulo' => '5 erros no PGR'])
            ->assertJsonFragment(['id' => $dia.'-2']);
        $this->api('GET', '/pedidos')->assertJsonCount(1)->assertJsonFragment(['titulo' => '5 erros no PGR', 'publico' => ['consultoria']]);
        $this->actingAs($usuario)->post('/calendario/temas', ['data' => $dia, 'titulo' => 'x', 'publico' => ['hacker']])->assertSessionHasErrors('publico.0');
    }

    public function test_semana_vazia_aparece_com_novo_tema(): void
    {
        $this->actingAs($this->usuario())->get('/calendario?mes='.now()->addMonths(2)->format('Y-m'))
            ->assertSee('Nenhum tema nesta semana')->assertSee('+ Novo tema nesta semana');
    }
}
