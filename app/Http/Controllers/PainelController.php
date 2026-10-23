<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/** Telas da Central. Aprovar, Calendário e Publicados ganham conteúdo nas próximas etapas. */
class PainelController extends Controller
{
    public function inicio(Request $request): View
    {
        return view('inicio', ['marca' => $request->attributes->get('marca')]);
    }

    public function aprovar(): View
    {
        return view('em-breve', [
            'titulo' => 'Para aprovar',
            'texto' => 'Nada esperando você. Quando a produção de quinta terminar, os carrosséis aparecem aqui para você aprovar.',
        ]);
    }

    public function calendario(): View
    {
        return view('em-breve', [
            'titulo' => 'Calendário',
            'texto' => 'O calendário de novembro chega aqui junto com a tela Para aprovar.',
        ]);
    }

    public function publicados(): View
    {
        return view('em-breve', [
            'titulo' => 'Publicados',
            'texto' => 'Os posts publicados e os números de cada um aparecem aqui.',
        ]);
    }
}
