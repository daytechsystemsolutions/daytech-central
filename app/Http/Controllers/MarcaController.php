<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramErro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarcaController extends Controller
{
    public function index(): View
    {
        return view('marcas');
    }

    /** Troca a marca aberta (seletor do topo). */
    public function escolher(Request $request, Marca $marca): RedirectResponse
    {
        abort_unless($marca->ativa, 404);
        $request->session()->put('marca', $marca->slug);

        return redirect()->route('inicio');
    }

    /** Liga ou desliga "Postar sozinho no horário" (só posta o que você aprovou). */
    public function postagem(Request $request, Marca $marca): RedirectResponse
    {
        $ligar = $request->boolean('ligar');
        if ($ligar && ! $marca->instagramConectado()) {
            return back()->with('aviso', 'Conecte o Instagram desta marca antes de ligar a postagem.');
        }
        $marca->forceFill(['postagem_automatica' => $ligar, 'postagem_ligada_em' => $ligar ? now() : null])->save();

        return back()->with('ok', $ligar
            ? 'Postagem ligada: a Central posta no horário o que você aprovou, mesmo com o computador desligado.'
            : 'Postagem desligada: nada sai sozinho.');
    }

    /** Confere de novo, na API, se o Instagram da marca continua ligado. */
    public function conferir(Marca $marca, InstagramClient $instagram): RedirectResponse
    {
        if (blank($marca->instagram_conta_id) || blank($marca->instagram_token)) {
            return back()->with('aviso', "{$marca->nome} ainda não tem Instagram conectado.");
        }

        try {
            $usuario = $instagram->conferirConta($marca->instagram_conta_id, $marca->instagram_token);
            $marca->forceFill(['instagram_usuario' => $usuario, 'instagram_verificado_em' => now(), 'instagram_erro' => null])->save();

            return back()->with('ok', "Instagram de {$marca->nome} conectado: @{$usuario}.");
        } catch (InstagramErro $e) {
            $marca->forceFill(['instagram_erro' => $e->getMessage()])->save();

            return back()->with('aviso', $e->getMessage());
        }
    }
}
