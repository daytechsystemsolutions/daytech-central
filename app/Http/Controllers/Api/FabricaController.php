<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarioItem;
use App\Models\Conteudo;
use App\Models\Marca;
use App\Services\Conteudo\RecebeConteudo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API da fábrica (o kit no PC): manda calendário e carrosséis, lê as decisões do usuário.
 * Os formatos de saída são os que o kit já entende (`./video calendario aplicar` e `./video fluxo aplicar`).
 */
class FabricaController extends Controller
{
    /** Calendário do kit -> Central. Atualiza tema, data e estado; a decisão do tema (trocar/tirar) é da Central. */
    public function enviarCalendario(Request $request, Marca $marca): JsonResponse
    {
        $dados = $request->validate([
            'itens' => 'required|array|max:500',
            'itens.*.id' => 'required|string|max:40',
            'itens.*.data' => 'required|date',
            'itens.*.hora' => 'nullable|string|max:5',
            'itens.*.titulo' => 'required|string|max:200',
            'itens.*.ideia' => 'nullable|string|max:2000',
            'itens.*.publico' => 'nullable|array',
            'itens.*.tipo' => 'nullable|string|max:30',
            'itens.*.estado' => 'nullable|string|max:30',
            'itens.*.carrossel' => 'nullable|string|max:120',
            'itens.*.sugerido_ia' => 'nullable|boolean',
            'itens.*.teste' => 'nullable|boolean',
            'itens.*.motivo' => 'nullable|string|max:300',
            'itens.*.pauta' => 'nullable|string|max:80',
        ]);

        $contagem = DB::transaction(function () use ($marca, $dados) {
            foreach ($dados['itens'] as $i) {
                $item = CalendarioItem::firstOrNew(['marca_id' => $marca->id, 'chave' => $i['id']]);
                $item->fill([
                    'data' => $i['data'], 'hora' => $i['hora'] ?? '11:40', 'titulo' => $i['titulo'],
                    'ideia' => $i['ideia'] ?? null, 'publico' => $i['publico'] ?? [], 'tipo' => $i['tipo'] ?? null,
                    'estado' => $i['estado'] ?? 'proposto', 'carrossel' => $i['carrossel'] ?? $item->carrossel,
                    'sugerido_ia' => (bool) ($i['sugerido_ia'] ?? false), 'teste' => (bool) ($i['teste'] ?? false),
                    'motivo' => $i['motivo'] ?? null, 'pauta_chave' => $i['pauta'] ?? $item->pauta_chave,
                ]);
                // Passou da fase de tema (produzido, aprovado...): a decisão de tema e o pedido de produção acabaram.
                if (! $item->podeMexer()) {
                    $item->fill(['decisao' => null, 'nota' => null, 'producao_pedida_em' => null, 'producao_iniciada_em' => null]);
                }
                $item->save();
            }

            return count($dados['itens']);
        });

        return response()->json(['ok' => true, 'itens' => $contagem]);
    }

    /** Decisões de tema para `./video calendario aplicar`. */
    public function decisoesCalendario(Marca $marca): JsonResponse
    {
        $mapa = ['manter' => 'manter', 'trocar' => 'trocar', 'tirar' => 'tirar'];

        return response()->json($marca->calendario()->whereNotNull('decisao')->get()
            ->map(fn (CalendarioItem $i) => ['id' => $i->chave, 'decisao' => $mapa[$i->decisao] ?? $i->decisao,
                'nota' => $i->nota, 'decidido_em' => $i->decidido_em?->toIso8601String()])
            ->values());
    }

    public function enviarConteudo(Request $request, Marca $marca, RecebeConteudo $recebe): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'titulo' => 'required|string|max:200',
            'calendario_chave' => 'nullable|string|max:40',
            'data' => 'nullable|date',
            'hora' => 'nullable|string|max:5',
            'publico' => 'nullable|array',
            'instagram' => 'nullable|string|max:2200',
            'linkedin' => 'nullable|string|max:3000',
            'conferencia' => 'nullable|array',
            'fontes' => 'nullable|array',
            'versao' => 'required|integer|min:1|max:999',
            'ajuste_feito' => 'nullable|string|max:1000',
            'laminas' => 'nullable|array',
            'laminas.*' => 'string|max:11200000', // 8 MB em base64
            'pdf' => 'nullable|string|max:34000000', // 25 MB em base64
            'publicado' => 'nullable|array',
            'publicado.link' => 'nullable|url|max:300',
            'publicado.em' => 'nullable|date',
        ]);

        $conteudo = $recebe->receber($marca, $dados);

        return response()->json(['ok' => true, 'nome' => $conteudo->nome, 'versao' => $conteudo->versao, 'estado' => $conteudo->estado]);
    }

    /**
     * Decisões dos carrosséis para `./video fluxo aplicar`. "aguardando" = a aprovação foi desfeita:
     * o kit tira o `aprovado:` do carrossel.yaml.
     */
    public function decisoesConteudos(Marca $marca): JsonResponse
    {
        return response()->json($marca->conteudos()->where('estado', '!=', 'publicado')->get()
            ->map(fn (Conteudo $c) => [
                'id' => $c->nome,
                'decisao' => $c->decisao ?? 'aguardando',
                'nota' => $c->nota,
                'versao_decidida' => $c->versao_decidida,
                'calendario_id' => $c->calendario_chave,
                'data' => $c->agendado_para?->toDateString(),
                'hora' => $c->agendado_para?->format('H:i'),
                'versao' => $c->versao,
                'hash_laminas' => $c->hash_laminas,
            ])->values());
    }

    /** Temas com "Produzir agora" pedido na Central e ainda não produzidos. */
    public function pedidos(Marca $marca): JsonResponse
    {
        return response()->json($marca->calendario()->whereNotNull('producao_pedida_em')->orderBy('data')->get()
            ->filter(fn (CalendarioItem $i) => $i->podeProduzir())
            ->map(fn (CalendarioItem $i) => $this->itemParaKit($i) + [
                'pedido_em' => $i->producao_pedida_em->toIso8601String(),
                'iniciado_em' => $i->producao_iniciada_em?->toIso8601String(),
            ])->values());
    }

    public function iniciarPedido(Marca $marca, string $chave): JsonResponse
    {
        $item = $marca->calendario()->where('chave', $chave)->whereNotNull('producao_pedida_em')->firstOrFail();
        $item->forceFill(['producao_iniciada_em' => $item->producao_iniciada_em ?? now()])->save();

        return response()->json(['ok' => true]);
    }

    /** Temas criados na Central ("+ Novo tema"), para o kit pôr no calendario.yaml. */
    public function temasDaCentral(Marca $marca): JsonResponse
    {
        return response()->json($marca->calendario()->where('origem', 'central')->orderBy('data')->get()
            ->map(fn (CalendarioItem $i) => $this->itemParaKit($i))->values());
    }

    private function itemParaKit(CalendarioItem $i): array
    {
        return ['id' => $i->chave, 'data' => $i->data->toDateString(), 'hora' => $i->hora, 'titulo' => $i->titulo,
            'ideia' => $i->ideia, 'publico' => $i->publico ?? [], 'tipo' => $i->tipo, 'estado' => $i->estado, 'pauta' => $i->pauta_chave];
    }

    public function ideias(Marca $marca): JsonResponse
    {
        return response()->json($marca->ideias()->latest()->get(['id', 'texto', 'created_at']));
    }
}
