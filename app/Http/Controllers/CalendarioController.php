<?php

namespace App\Http\Controllers;

use App\Models\CalendarioItem;
use App\Models\Ideia;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Calendário do mês: trocar ou tirar um tema antes da produção. E a caixa de ideias da marca. */
class CalendarioController extends Controller
{
    public function index(Request $request): View
    {
        $marca = $request->attributes->get('marca');
        $mes = $this->mes($request->query('mes'), $marca);

        // Intervalo meio-aberto: a data fica gravada com hora (00:00:00) e "<= último dia" perderia o dia 30/31.
        $itens = $marca->calendario()->where('data', '>=', $mes->startOfMonth()->toDateString())
            ->where('data', '<', $mes->addMonth()->startOfMonth()->toDateString())
            ->orderBy('data')->orderBy('hora')->get();
        $conteudos = $marca->conteudos()->whereIn('nome', $itens->pluck('carrossel')->filter())->get()->keyBy('nome');

        $semanas = $itens->groupBy(fn (CalendarioItem $i) => $i->data->startOfWeek()->toDateString());

        return view('calendario', [
            'mes' => $mes,
            'semanas' => $semanas,
            'conteudos' => $conteudos,
            'ideias' => $marca->ideias()->latest()->get(),
        ]);
    }

    public function trocar(Request $request, CalendarioItem $item): RedirectResponse
    {
        $nota = $request->validate(['nota' => 'required|string|min:3|max:500'], ['nota.required' => 'Escreva o tema que entra no lugar.'])['nota'];

        return $this->decidir($item, 'trocar', $nota, 'Troca pedida. O tema novo entra na próxima produção.');
    }

    public function tirar(CalendarioItem $item): RedirectResponse
    {
        return $this->decidir($item, 'tirar', null, 'Tema tirado do calendário.');
    }

    public function manter(CalendarioItem $item): RedirectResponse
    {
        return $this->decidir($item, 'manter', null, 'Tema de volta ao calendário.');
    }

    public function novaIdeia(Request $request): RedirectResponse
    {
        $texto = $request->validate(['texto' => 'required|string|min:3|max:1000'], ['texto.required' => 'Escreva a ideia.'])['texto'];
        $request->attributes->get('marca')->ideias()->create(['texto' => $texto]);

        return back()->with('ok', 'Ideia guardada. A produção de quinta olha esta lista.');
    }

    public function apagarIdeia(Request $request, Ideia $ideia): RedirectResponse
    {
        abort_unless($ideia->marca_id === $request->attributes->get('marca')->id, 404);
        $ideia->delete();

        return back()->with('ok', 'Ideia apagada.');
    }

    private function decidir(CalendarioItem $item, string $decisao, ?string $nota, string $mensagem): RedirectResponse
    {
        if (! $item->podeMexer()) {
            return back()->with('aviso', 'Este tema já foi produzido. Para mudar, use Para aprovar.');
        }
        $item->forceFill(['decisao' => $decisao, 'nota' => $nota, 'decidido_em' => now()])->save();

        return redirect()->route('calendario', ['mes' => $item->data->format('Y-m')])->with('ok', $mensagem);
    }

    private function mes(?string $pedido, $marca): CarbonImmutable
    {
        if ($pedido && preg_match('/^\d{4}-\d{2}$/', $pedido)) {
            return CarbonImmutable::createFromFormat('Y-m-d', $pedido.'-01')->startOfMonth();
        }
        // Sem mês escolhido: o mês do próximo tema (no fim de outubro já mostra novembro).
        $proximo = $marca->calendario()->where('data', '>=', now()->toDateString())->orderBy('data')->value('data');

        return CarbonImmutable::parse($proximo ?? now())->startOfMonth();
    }
}
