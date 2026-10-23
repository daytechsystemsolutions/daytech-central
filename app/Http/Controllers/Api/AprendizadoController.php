<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use App\Models\Post;
use App\Services\Resultados\Aprendizados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Parte de IA da rota automática (quem faz a IA é o Claude, pela fábrica): classificar os posts, escrever a leitura
 * do 7º dia e as regras da quinta. Tudo que volta daqui é dado, nunca instrução.
 */
class AprendizadoController extends Controller
{
    public const PILARES = ['acelerar', 'facilitar', 'organizar', 'ensinar', 'institucional'];

    public const PUBLICOS = ['consultoria', 'clinica', 'sesmt', 'autonomo', 'psicologo', 'geral'];

    public const FORMATOS = ['passo_a_passo', 'lista', 'antes_depois', 'demonstracao', 'dado', 'pergunta', 'depoimento', 'institucional', 'outro'];

    public const CHAMADAS = ['comente', 'salve', 'compartilhe', 'link_bio', 'whatsapp', 'nenhuma', 'outra'];

    /** Posts ainda sem "como foi feito" (os antigos e os que chegarem). */
    public function paraClassificar(Marca $marca): JsonResponse
    {
        return response()->json($marca->posts()->with('conteudo')->whereNull('como_foi_feito')->orderBy('publicado_em')->get()
            ->map(fn (Post $p) => $this->resumoDoPost($p))->values());
    }

    public function classificar(Request $request, Marca $marca, string $igId): JsonResponse
    {
        $post = $this->post($marca, $igId);
        $dados = $request->validate([
            'pilar' => ['required', Rule::in(self::PILARES)],
            'publico_principal' => ['required', Rule::in(self::PUBLICOS)],
            'formato' => ['required', Rule::in(self::FORMATOS)],
            'chamada' => ['required', Rule::in(self::CHAMADAS)],
            'tema' => 'required|string|max:120',
            'estimado' => 'required|boolean',
        ]);
        $estimado = (bool) $dados['estimado'];
        unset($dados['estimado']);
        $post->forceFill(['como_foi_feito' => $dados, 'como_foi_feito_estimado' => $estimado])->save();

        return response()->json(['ok' => true]);
    }

    /** Posts com nota fechada (7º dia) e ainda sem leitura. */
    public function paraLer(Marca $marca): JsonResponse
    {
        return response()->json($marca->posts()->with(['conteudo', 'ultimaMetrica', 'comentarios'])
            ->whereNotNull('nota_fechada_em')->whereNull('leitura')->orderByDesc('publicado_em')->limit(20)->get()
            ->map(fn (Post $p) => $this->resumoDoPost($p) + [
                'nota' => $p->nota,
                'partes' => $p->nota_partes['partes'] ?? [],
                'numeros' => $p->ultimaMetrica?->only(['visualizacoes', 'alcance', 'curtidas', 'comentarios', 'salvos', 'compartilhamentos', 'interacoes', 'novos_seguidores', 'visitas_perfil', 'acoes_perfil']),
                'comentarios' => $p->comentarios->pluck('texto')->take(20)->values(),
            ])->values());
    }

    public function ler(Request $request, Marca $marca, string $igId): JsonResponse
    {
        $texto = $request->validate(['texto' => 'required|string|min:20|max:700'])['texto'];
        $this->post($marca, $igId)->forceFill(['leitura' => $texto, 'leitura_em' => now()])->save();

        return response()->json(['ok' => true]);
    }

    /** O que os números mostram (os mesmos blocos da tela) + as regras de agora + o histórico resumido. */
    public function aprendizados(Marca $marca, Aprendizados $aprendizados): JsonResponse
    {
        $posts = $marca->posts()->with('conteudo')->whereNotNull('nota')->latest('publicado_em')->limit(60)->get();

        return response()->json([
            'fase' => $marca->faseAtual(),
            'seguidores' => $marca->seguidores(),
            'resumo' => $aprendizados->daMarca($marca),
            'regras' => $marca->regras()->where('vigente', true)->get(['chave', 'tipo', 'texto', 'evidencia', 'ativa']),
            'posts' => $posts->map(fn (Post $p) => [
                'ig_id' => $p->ig_id, 'titulo' => $p->titulo(), 'publicado_em' => $p->publicado_em->toIso8601String(),
                'tipo_midia' => $p->tipo_midia, 'nota' => $p->nota, 'fechada' => $p->nota_fechada_em !== null,
                'como_foi_feito' => $p->como_foi_feito, 'leitura' => $p->leitura,
            ])->values(),
        ]);
    }

    /** Troca a lista de regras da semana. Regra que continua (mesma chave) mantém o "desligada" do usuário. */
    public function regras(Request $request, Marca $marca): JsonResponse
    {
        $dados = $request->validate([
            'regras' => 'present|array|max:15',
            'regras.*.chave' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'regras.*.tipo' => ['required', Rule::in(['faca', 'evite', 'teste'])],
            'regras.*.texto' => 'required|string|max:300',
            'regras.*.evidencia' => 'nullable|string|max:200',
        ]);

        DB::transaction(function () use ($marca, $dados) {
            $marca->regras()->update(['vigente' => false]);
            foreach ($dados['regras'] as $r) {
                $regra = $marca->regras()->firstOrNew(['chave' => $r['chave']]);
                $regra->fill(['tipo' => $r['tipo'], 'texto' => $r['texto'], 'evidencia' => $r['evidencia'] ?? null, 'vigente' => true])->save();
            }
        });

        return response()->json(['ok' => true, 'regras' => count($dados['regras'])]);
    }

    /** O que a pesquisa precisa: comentários recentes, ideias, calendário dos próximos 60 dias e o banco atual. */
    public function insumosDaPesquisa(Marca $marca): JsonResponse
    {
        $posts = $marca->posts()->where('publicado_em', '>=', now()->subDays(90))->pluck('id');

        return response()->json([
            'comentarios' => \App\Models\PostComentario::whereIn('post_id', $posts)->latest('comentado_em')->limit(100)->pluck('texto'),
            'ideias' => $marca->ideias()->latest()->pluck('texto'),
            'calendario' => $marca->calendario()->where('data', '>=', now()->toDateString())->where('data', '<', now()->addDays(60)->toDateString())
                ->orderBy('data')->get(['chave', 'data', 'titulo', 'publico', 'estado', 'decisao', 'origem', 'pauta_chave']),
            'pautas' => $marca->pautas()->where('estado', '!=', 'descartada')->orderByDesc('potencial')->get(['chave', 'titulo', 'potencial', 'estado', 'calendario_chave']),
        ]);
    }

    /**
     * Banco de pautas da semana. Pauta nova entra; a que já existe (mesma chave) atualiza o potencial e as fontes,
     * sem mexer no estado que o usuário deu (no calendário, descartada).
     */
    public function pautas(Request $request, Marca $marca): JsonResponse
    {
        $dados = $request->validate([
            'pautas' => 'required|array|max:40',
            'pautas.*.chave' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'pautas.*.titulo' => 'required|string|max:200',
            'pautas.*.ideia' => 'nullable|string|max:1500',
            'pautas.*.pilar' => ['nullable', Rule::in(self::PILARES)],
            'pautas.*.publico' => ['nullable', Rule::in(self::PUBLICOS)],
            'pautas.*.formato' => ['required', Rule::in(['carrossel', 'reels'])],
            'pautas.*.partes' => 'required|array',
            'pautas.*.partes.*.pontos' => 'required|integer|min:0|max:100',
            'pautas.*.partes.*.resumo' => 'required|string|max:300',
            'pautas.*.fontes' => 'nullable|array|max:8',
            'pautas.*.fontes.*.titulo' => 'required|string|max:150',
            'pautas.*.fontes.*.url' => 'required|url|max:400',
            'pautas.*.prazo' => 'nullable|date',
            'pautas.*.calendario_chave' => 'nullable|string|max:40',
        ]);

        foreach ($dados['pautas'] as $p) {
            $partes = array_intersect_key($p['partes'], \App\Models\Pauta::PESOS);
            $pauta = $marca->pautas()->firstOrNew(['chave' => $p['chave']]);
            $pauta->fill([
                'titulo' => $p['titulo'], 'ideia' => $p['ideia'] ?? null, 'pilar' => $p['pilar'] ?? null,
                'publico' => $p['publico'] ?? null, 'formato' => $p['formato'], 'partes' => $partes,
                'potencial' => \App\Models\Pauta::calcular($partes), 'fontes' => $p['fontes'] ?? [], 'prazo' => $p['prazo'] ?? null,
            ]);
            // Pauta que já é um tema do calendário (avaliada pela pesquisa): liga as duas.
            if (! empty($p['calendario_chave'])) {
                $pauta->fill(['estado' => 'no_calendario', 'calendario_chave' => $p['calendario_chave']]);
                $marca->calendario()->where('chave', $p['calendario_chave'])->update(['pauta_chave' => $p['chave']]);
            }
            $pauta->save();
        }

        return response()->json(['ok' => true, 'pautas' => count($dados['pautas'])]);
    }

    /** Capa do post em base64, para o Claude olhar ao classificar ou ler. */
    public function capa(Marca $marca, string $igId): JsonResponse
    {
        $post = $this->post($marca, $igId);
        abort_unless($post->capa_caminho && Storage::exists($post->capa_caminho), 404);

        return response()->json(['jpeg_base64' => base64_encode(Storage::get($post->capa_caminho))]);
    }

    private function post(Marca $marca, string $igId): Post
    {
        return $marca->posts()->where('ig_id', $igId)->firstOrFail();
    }

    private function resumoDoPost(Post $p): array
    {
        return [
            'ig_id' => $p->ig_id, 'tipo_midia' => $p->tipo_midia, 'publicado_em' => $p->publicado_em->toIso8601String(),
            'legenda' => $p->legenda, 'permalink' => $p->permalink, 'carrossel' => $p->conteudo?->nome,
            'como_foi_feito' => $p->como_foi_feito, 'tem_capa' => (bool) $p->capa_caminho,
        ];
    }
}
