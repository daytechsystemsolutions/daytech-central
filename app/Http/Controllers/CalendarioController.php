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

        // Todas as semanas que tocam o mês (segunda a domingo), mesmo vazias: dá para criar tema nelas.
        $semanas = collect();
        for ($ini = $mes->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::MONDAY); $ini->lte($mes->endOfMonth()); $ini = $ini->addWeek()) {
            $semanas[$ini->toDateString()] = $itens->filter(fn (CalendarioItem $i) => $i->data->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString() === $ini->toDateString())->values();
        }

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

    /** "Produzir esta semana agora": a sincronização do kit (de hora em hora) pega e produz. */
    public function produzirSemana(Request $request, string $inicio): RedirectResponse
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio), 404);
        $ini = CarbonImmutable::parse($inicio)->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $pedidos = $request->attributes->get('marca')->calendario()
            ->where('data', '>=', $ini->toDateString())->where('data', '<', $ini->addWeek()->toDateString())
            ->whereNull('producao_pedida_em')->get()
            ->filter(fn (CalendarioItem $i) => $i->podeProduzir())
            ->each(fn (CalendarioItem $i) => $i->forceFill(['producao_pedida_em' => now()])->save());

        if ($pedidos->isEmpty()) {
            return back()->with('aviso', 'Nada para produzir nesta semana: crie um tema ou veja se já foram produzidos.');
        }

        return redirect()->route('calendario', ['mes' => $ini->format('Y-m')])
            ->with('ok', $this->textoPedido($pedidos->count()));
    }

    public function produzir(CalendarioItem $item): RedirectResponse
    {
        if (! $item->podeProduzir()) {
            return back()->with('aviso', 'Este tema já foi produzido ou foi tirado.');
        }
        $item->forceFill(['producao_pedida_em' => $item->producao_pedida_em ?? now()])->save();

        return redirect()->route('calendario', ['mes' => $item->data->format('Y-m')])->with('ok', $this->textoPedido(1));
    }

    public function cancelarProducao(CalendarioItem $item): RedirectResponse
    {
        if ($item->producao_iniciada_em) {
            return back()->with('aviso', 'A produção já começou. Quando chegar, você pode tirar em Para aprovar.');
        }
        $item->forceFill(['producao_pedida_em' => null])->save();

        return back()->with('ok', 'Pedido de produção cancelado.');
    }

    /** "+ Novo tema": entra no calendário daqui e o kit traz para o calendario.yaml na sincronização. */
    public function novoTema(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'data' => 'required|date|after_or_equal:today',
            'hora' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'titulo' => 'required|string|min:3|max:200',
            'ideia' => 'nullable|string|max:1000',
            'publico' => 'nullable|array',
            'publico.*' => 'in:'.implode(',', array_keys(\App\Models\Conteudo::PUBLICOS)),
            'produzir' => 'nullable|boolean',
        ], ['titulo.required' => 'Escreva o tema.', 'data.after_or_equal' => 'Escolha hoje ou um dia depois.']);

        $marca = $request->attributes->get('marca');
        $chave = $dados['data'];
        for ($n = 2; $marca->calendario()->where('chave', $chave)->exists(); $n++) {
            $chave = $dados['data'].'-'.$n;
        }

        $item = $marca->calendario()->create([
            'chave' => $chave, 'data' => $dados['data'], 'hora' => $dados['hora'] ?? '11:40', 'titulo' => $dados['titulo'],
            'ideia' => $dados['ideia'] ?? null, 'publico' => $dados['publico'] ?? [], 'estado' => 'tema_aprovado', 'origem' => 'central',
        ]);
        if ($request->boolean('produzir')) {
            $item->forceFill(['producao_pedida_em' => now()])->save();
        }

        return redirect()->route('calendario', ['mes' => $item->data->format('Y-m')])
            ->with('ok', $request->boolean('produzir') ? 'Tema criado. '.$this->textoPedido(1) : 'Tema criado no calendário.');
    }

    private function textoPedido(int $n): string
    {
        $quantos = $n === 1 ? '1 carrossel pedido' : "{$n} carrosséis pedidos";

        return "{$quantos}. A produção começa na próxima sincronização (até 1 hora, das 8h às 20h, com o computador ligado) e você recebe um aviso quando ficar pronto.";
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
