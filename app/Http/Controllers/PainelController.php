<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PainelController extends Controller
{
    public function inicio(Request $request): View
    {
        $marca = $request->attributes->get('marca');

        return view('inicio', [
            'marca' => $marca,
            'esperando' => $marca->conteudos()->where('estado', 'aguardando')->orderBy('agendado_para')->get(),
            'proximos' => $marca->calendario()->where('data', '>=', now()->toDateString())->where('estado', '!=', 'tirado')
                ->where(fn ($q) => $q->whereNull('decisao')->orWhere('decisao', '!=', 'tirar'))
                ->orderBy('data')->orderBy('hora')->limit(3)->get(),
            'conteudos' => $marca->conteudos()->get()->keyBy('nome'),
            'ultimo' => $marca->conteudos()->where('estado', 'publicado')->latest('publicado_em')->first(),
            'falhas' => $marca->conteudos()->where('estado', 'falhou')->count(),
        ]);
    }

    public function publicados(Request $request): View
    {
        return view('publicados', [
            'publicados' => $request->attributes->get('marca')->conteudos()->where('estado', 'publicado')->latest('publicado_em')->get(),
        ]);
    }
}
