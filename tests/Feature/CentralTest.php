<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CentralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('central:preparar')->assertSuccessful();
    }

    private function usuarioCom2fa(): User
    {
        return User::factory()->create(['two_factor_secret' => encrypt('SEGREDO'), 'two_factor_confirmed_at' => now()]);
    }

    public function test_visitante_vai_para_o_login_e_nao_ha_cadastro_aberto(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/marcas')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Entrar');
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_sem_2fa_confirmado_so_a_tela_de_seguranca_abre(): void
    {
        $usuario = User::factory()->create();

        foreach (['/', '/aprovar', '/calendario', '/publicados', '/marcas'] as $rota) {
            $this->actingAs($usuario)->get($rota)->assertRedirect('/seguranca');
        }
        $this->actingAs($usuario)->get('/seguranca')->assertRedirect('/user/confirm-password');
        $this->actingAs($usuario)->withSession(['auth.password_confirmed_at' => time()])
            ->get('/seguranca')->assertOk()->assertSee('Ative os 2 fatores');
    }

    public function test_com_2fa_as_telas_abrem(): void
    {
        $usuario = $this->usuarioCom2fa();

        $this->actingAs($usuario)->get('/')->assertOk()->assertSee('Nada esperando você')->assertSee('MedNext SST');
        $this->actingAs($usuario)->get('/marcas')->assertOk()->assertSee('LuPe Educa');
        $this->actingAs($usuario)->get('/aprovar')->assertOk();
    }

    public function test_cabecalhos_de_seguranca(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_trocar_de_marca_fica_na_sessao(): void
    {
        $usuario = $this->usuarioCom2fa();

        $this->actingAs($usuario)->post('/marcas/lupe/abrir')->assertRedirect('/');
        $this->actingAs($usuario)->get('/')->assertSee('carrosséis de LuPe Educa');
    }

    public function test_token_do_instagram_fica_criptografado_e_fora_da_tela(): void
    {
        $marca = Marca::where('slug', 'mednext')->first();
        $marca->forceFill(['instagram_conta_id' => '17841475341916085', 'instagram_token' => 'TOKEN-SECRETO-123', 'instagram_usuario' => 'mednext_sst', 'instagram_verificado_em' => now()])->save();

        $bruto = DB::table('marcas')->where('slug', 'mednext')->value('instagram_token');
        $this->assertNotSame('TOKEN-SECRETO-123', $bruto);
        $this->assertStringNotContainsString('TOKEN-SECRETO', $bruto);
        $this->assertSame('TOKEN-SECRETO-123', $marca->fresh()->instagram_token);
        $this->assertArrayNotHasKey('instagram_token', $marca->fresh()->toArray());

        $this->actingAs($this->usuarioCom2fa())->get('/marcas')->assertOk()->assertSee('@mednext_sst')->assertDontSee('TOKEN-SECRETO');
    }

    public function test_conferir_instagram_ok_e_com_erro(): void
    {
        $marca = Marca::where('slug', 'mednext')->first();
        $marca->forceFill(['instagram_conta_id' => '17841475341916085', 'instagram_token' => 'TOKEN-SECRETO-123'])->save();
        $usuario = $this->usuarioCom2fa();

        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['id' => '17841475341916085', 'username' => 'mednext_sst'])
            ->push(['error' => ['message' => 'Invalid OAuth access token TOKEN-SECRETO-123']], 400)]);
        $this->actingAs($usuario)->post('/marcas/mednext/conferir')->assertSessionHas('ok');
        $this->assertSame('mednext_sst', $marca->fresh()->instagram_usuario);
        $this->assertNotNull($marca->fresh()->instagram_verificado_em);

        $this->actingAs($usuario)->post('/marcas/mednext/conferir')->assertSessionHas('aviso');
        $erro = $marca->fresh()->instagram_erro;
        $this->assertStringContainsString('recusou', $erro);
        $this->assertStringNotContainsString('TOKEN-SECRETO', $erro);
    }

    public function test_preparar_nao_duplica_nem_sobrescreve(): void
    {
        Marca::where('slug', 'mednext')->update(['descricao' => 'mudada à mão']);
        $this->artisan('central:preparar')->assertSuccessful();

        $this->assertSame(2, Marca::count());
        $this->assertSame('mudada à mão', Marca::where('slug', 'mednext')->value('descricao'));
    }

    public function test_comando_zera_2fa(): void
    {
        $usuario = $this->usuarioCom2fa();
        $this->artisan('central:usuario', ['email' => $usuario->email, '--zerar-2fa' => true])->assertSuccessful();
        $this->assertNull($usuario->fresh()->two_factor_confirmed_at);
    }

    public function test_entrar_com_senha_e_codigo_do_autenticador(): void
    {
        $google = app(\PragmaRX\Google2FA\Google2FA::class);
        $segredo = $google->generateSecretKey();
        $usuario = User::factory()->create(['password' => 'senha-de-teste-123', 'two_factor_secret' => encrypt($segredo), 'two_factor_confirmed_at' => now()]);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'senha-de-teste-123'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => $google->getCurrentOtp($segredo)])->assertRedirect('/');
        $this->assertAuthenticatedAs($usuario);

        // Entrar com senha e código já vale como confirmação: Segurança abre sem pedir a senha de novo.
        $this->get('/seguranca')->assertOk();
    }

    public function test_desligar_ou_trocar_o_2fa_exige_a_senha(): void
    {
        $usuario = $this->usuarioCom2fa();

        $this->actingAs($usuario)->delete('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');
        $this->actingAs($usuario)->post('/user/two-factor-authentication', ['force' => 1])->assertRedirect('/user/confirm-password');
        $this->actingAs($usuario)->getJson('/user/two-factor-recovery-codes')->assertStatus(423);
        $this->assertNotNull($usuario->fresh()->two_factor_confirmed_at);
    }

    public function test_rotas_de_acao_tambem_exigem_2fa_e_marca_inativa_da_404(): void
    {
        $semDoisFatores = User::factory()->create();
        $this->actingAs($semDoisFatores)->post('/marcas/mednext/conferir')->assertRedirect('/seguranca');

        Marca::where('slug', 'lupe')->update(['ativa' => false]);
        $this->actingAs($this->usuarioCom2fa())->post('/marcas/lupe/abrir')->assertNotFound();
    }

    public function test_csp_sem_inline(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_trocar_senha_pelo_comando_derruba_as_sessoes(): void
    {
        $usuario = $this->usuarioCom2fa();
        DB::table('sessions')->insert(['id' => 'sessao-antiga', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time()]);
        $tokenAntigo = $usuario->remember_token;

        $this->artisan('central:usuario', ['email' => $usuario->email, '--zerar-2fa' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $usuario->id)->count());
        $this->assertNotSame($tokenAntigo, $usuario->fresh()->remember_token);
    }
}
