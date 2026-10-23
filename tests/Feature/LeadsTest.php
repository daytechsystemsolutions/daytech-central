<?php

namespace Tests\Feature;

use App\Models\ContaDia;
use App\Models\Lead;
use App\Models\Marca;
use App\Models\TokenFabrica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('central:preparar');
    }

    public function test_site_avisa_lead_com_a_chave_do_site_e_o_inicio_conta(): void
    {
        TokenFabrica::create(['nome' => 'site', 'hash' => TokenFabrica::hashDe(str_repeat('s', 64)), 'escopo' => 'site']);
        TokenFabrica::create(['nome' => 'kit', 'hash' => TokenFabrica::hashDe(str_repeat('k', 64)), 'escopo' => 'fabrica']);
        $url = '/api/site/marcas/mednext/leads';

        $this->postJson($url, ['fonte' => 'instagram'])->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('k', 64)])->postJson($url, ['fonte' => 'instagram'])->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('s', 64)])->getJson('/api/fabrica/marcas/mednext/ideias')->assertUnauthorized();

        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('s', 64)])
            ->postJson($url, ['fonte' => 'instagram', 'meio' => 'bio', 'campanha' => 'perfil', 'email' => 'nao@guardar.com'])->assertCreated();

        $lead = Lead::first();
        $this->assertSame('bio', $lead->meio);
        $this->assertArrayNotHasKey('email', $lead->getAttributes());

        $marca = Marca::where('slug', 'mednext')->first();
        ContaDia::create(['marca_id' => $marca->id, 'dia' => today()->subDay(), 'seguidores' => 82, 'posts' => 30]);
        $usuario = User::factory()->create(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()]);
        $this->actingAs($usuario)->get('/')->assertSee('Leads do Instagram')->assertSee('nos últimos 7 dias · 1 em');
        $this->actingAs($usuario)->get('/resultados')->assertSee('1 lead chegou');
    }

    public function test_comando_gera_a_chave_do_site(): void
    {
        $this->artisan('central:token-fabrica', ['nome' => 'site', '--site' => true])
            ->expectsOutputToContain('CENTRAL_LEADS_URL=')->expectsOutputToContain('CENTRAL_LEADS_TOKEN=')->assertSuccessful();
        $this->assertSame('site', TokenFabrica::first()->escopo);
    }
}
