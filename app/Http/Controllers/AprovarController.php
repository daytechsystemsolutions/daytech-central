<?php

namespace App\Http\Controllers;

use App\Models\Conteudo;
use App\Models\ConteudoLamina;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Para aprovar: o usuário vê o carrossel pronto e decide. "Aprovar e agendar" vale como o "pode postar"
 * daquele carrossel, daquela versão (decisão de 22/10/2026).
 */
class AprovarController extends Controller
{
    /** Lista por semana: o que espera você e o que já foi decidido e ainda não saiu. */
    public function index(Request $request): View
    {
        $marca = $request->attributes->get('marca');
        $conteudos = $marca->conteudos()->whereIn('estado', ['aguardando', 'ajuste', 'agendado', 'falhou', 'roteiro_aprovado'])
            ->orderByRaw('agendado_para is null')->orderBy('agendado_para')->get();

        return view('aprovar.lista', [
            'semanas' => $conteudos->groupBy(fn (Conteudo $c) => $c->agendado_para?->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString() ?? 'sem-data'),
            'esperando' => $conteudos->where('estado', 'aguardando')->count(),
            'pedidos' => $marca->calendario()->whereNotNull('producao_pedida_em')->count(),
        ]);
    }

    public function ver(Request $request, Conteudo $conteudo): View
    {
        $this->abrirMarcaDo($request, $conteudo);
        $fila = $conteudo->marca->conteudos()->where(fn ($q) => $q->where('estado', 'aguardando')->orWhere('id', $conteudo->id))
            ->orderBy('agendado_para')->pluck('id')->values();
        $posicao = $fila->search($conteudo->id);

        return view('aprovar.ver', [
            'conteudo' => $conteudo,
            'laminas' => $conteudo->laminasAtuais(),
            'posicao' => $posicao + 1,
            'total' => $fila->count(),
            'anterior' => $posicao > 0 ? $fila[$posicao - 1] : null,
            'proximo' => $fila[$posicao + 1] ?? null,
        ]);
    }

    public function aprovar(Request $request, Conteudo $conteudo): RedirectResponse
    {
        $semHorario = ! $conteudo->agendado_para || $conteudo->agendado_para->isPast();
        $dados = $request->validate([
            'versao' => 'required|integer',
            'midia' => 'nullable|string|max:64',
            'quando' => [$semHorario ? 'required' : 'nullable', 'date', 'after:now'],
        ], ['quando.required' => 'Escolha quando postar.', 'quando.after' => 'Escolha um horário no futuro.']);

        if ($conteudo->ehRoteiro()) {
            return back()->with('aviso', 'Este Reels ainda está no roteiro: aprove o roteiro primeiro.');
        }
        if ($conteudo->ehReels() && ! ($conteudo->video_caminho && Storage::exists($conteudo->video_caminho))) {
            return back()->with('aviso', 'Este Reels está sem o vídeo. Peça um ajuste.');
        }
        if (! $conteudo->ehReels() && $conteudo->laminasAtuais()->isEmpty()) {
            return back()->with('aviso', 'Este carrossel está sem lâminas. Peça um ajuste.');
        }

        $quando = $semHorario
            ? \Carbon\CarbonImmutable::parse($dados['quando'], config('app.timezone'))
            : $conteudo->agendado_para;

        // Só aprova se continuar esperando e na mesma versão que estava na tela (o kit pode ter mandado outra).
        $aprovado = Conteudo::whereKey($conteudo->id)->where('estado', 'aguardando')->where('versao', (int) $dados['versao'])
            ->where(fn ($q) => $q->where('tipo', 'carrossel')->orWhere('fase', 'video'))
            // A mídia é a mesma que estava na tela (o kit pode ter reenviado a mesma versão com outro arquivo).
            ->where(fn ($q) => filled($dados['midia'] ?? null)
                ? $q->where($conteudo->ehReels() ? 'video_hash' : 'hash_laminas', $dados['midia'])
                : $q->whereNull($conteudo->ehReels() ? 'video_hash' : 'hash_laminas'))
            ->update(['estado' => 'agendado', 'decisao' => 'aprovar', 'nota' => null, 'versao_decidida' => (int) $dados['versao'],
                'decidido_em' => now(), 'agendado_para' => $quando]);

        if (! $aprovado) {
            return back()->with('aviso', 'Este '.$conteudo->tipoTexto().' mudou ou já foi decidido. Confira a versão atual.');
        }

        $texto = $quando->locale('pt_BR')->translatedFormat('D d/m').' às '.$quando->format('H\hi');

        return redirect()->route('aprovar.ver', $conteudo)->with('ok', "Agendado: {$texto}. Você pode desfazer até a hora.");
    }

    /**
     * Primeira aprovação do Reels: o roteiro (fala e o que aparece, cena por cena). Não agenda nada: libera a
     * gravação. O vídeo pronto volta para "Para aprovar" e só ele vale como o "pode postar".
     */
    public function aprovarRoteiro(Request $request, Conteudo $conteudo): RedirectResponse
    {
        $versao = (int) $request->validate(['versao' => 'required|integer'])['versao'];
        $aprovado = Conteudo::whereKey($conteudo->id)->where('estado', 'aguardando')->where('versao', $versao)
            ->where('tipo', 'reels')->where('fase', 'roteiro')
            ->update(['estado' => 'roteiro_aprovado', 'decisao' => 'aprovar_roteiro', 'nota' => null, 'versao_decidida' => $versao,
                'roteiro_aprovado_versao' => $versao, 'decidido_em' => now()]);

        if (! $aprovado) {
            return back()->with('aviso', 'Este roteiro mudou ou já foi decidido. Confira a versão atual.');
        }

        return redirect()->route('aprovar.ver', $conteudo)->with('ok', 'Roteiro aprovado. O vídeo é gravado e volta aqui para você aprovar antes de postar.');
    }

    public function ajuste(Request $request, Conteudo $conteudo): RedirectResponse
    {
        $nota = $request->validate(['nota' => 'required|string|min:3|max:1000'], ['nota.required' => 'Escreva o que precisa mudar.'])['nota'];
        if (! in_array($conteudo->estado, ['aguardando', 'agendado', 'roteiro_aprovado'], true)) {
            return back()->with('aviso', 'Este '.$conteudo->tipoTexto().' não pode mais ser ajustado.');
        }

        $this->decidir($conteudo, 'ajuste', 'ajuste', $nota);
        if ($conteudo->ehRoteiro()) {
            $conteudo->forceFill(['roteiro_aprovado_versao' => null])->save(); // o roteiro muda: não grava o vídeo antigo
        }

        return redirect()->route('aprovar.ver', $conteudo)->with('ok', 'Pedido enviado. A nova versão volta aqui para você aprovar.');
    }

    public function tirar(Conteudo $conteudo): RedirectResponse
    {
        if (! in_array($conteudo->estado, ['aguardando', 'agendado', 'ajuste', 'roteiro_aprovado'], true)) {
            return back()->with('aviso', 'Este '.$conteudo->tipoTexto().' não pode mais ser tirado.');
        }

        $this->decidir($conteudo, 'tirado', 'tirar');
        if ($conteudo->ehReels()) {
            $conteudo->forceFill(['roteiro_aprovado_versao' => null])->save(); // tirado: o kit não grava nem manda vídeo
        }

        return redirect()->route('aprovar.ver', $conteudo)->with('ok', 'Tirado. Este post não vai ser publicado.');
    }

    public function desfazer(Conteudo $conteudo): RedirectResponse
    {
        if (! $conteudo->podeDesfazer()) {
            return back()->with('aviso', 'Não dá mais para desfazer: o horário do post já passou.');
        }

        $conteudo->forceFill(['estado' => 'aguardando', 'decisao' => null, 'nota' => null, 'versao_decidida' => null, 'decidido_em' => null])->save();
        if ($conteudo->ehRoteiro()) {
            $conteudo->forceFill(['roteiro_aprovado_versao' => null])->save();
        }

        return redirect()->route('aprovar.ver', $conteudo)->with('ok', 'Desfeito. Voltou para Para aprovar.');
    }

    /**
     * Para o Instagram: só lâmina da versão atual de carrossel aprovado (o link já vem assinado e com validade).
     * "postando" entra: é nessa hora que o Instagram baixa as imagens.
     */
    public function laminaPublica(Conteudo $conteudo, ConteudoLamina $lamina): StreamedResponse
    {
        abort_unless($lamina->conteudo_id === $conteudo->id && $lamina->versao === $conteudo->versao
            && in_array($conteudo->estado, ['agendado', 'postando', 'publicado'], true) && Storage::exists($lamina->caminho), 404);

        return Storage::response($lamina->caminho, $lamina->ordem.'.jpg', ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
    }

    /** Para o Instagram: o vídeo do Reels agendado (link assinado com validade; aceita Range). */
    public function videoPublico(Conteudo $conteudo): BinaryFileResponse
    {
        abort_unless($conteudo->ehReels() && in_array($conteudo->estado, ['postando', 'publicado'], true)
            && $conteudo->video_caminho && Storage::exists($conteudo->video_caminho), 404);

        return response()->file(Storage::path($conteudo->video_caminho), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'no-store']);
    }

    public function capaPublica(Conteudo $conteudo): BinaryFileResponse
    {
        abort_unless($conteudo->ehReels() && in_array($conteudo->estado, ['postando', 'publicado'], true)
            && $conteudo->capa_caminho && Storage::exists($conteudo->capa_caminho), 404);

        return response()->file(Storage::path($conteudo->capa_caminho), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
    }

    /** Vídeo para tocar na tela de aprovação (Range: o Safari do iPhone só toca assim). */
    public function video(Conteudo $conteudo): BinaryFileResponse
    {
        abort_unless($conteudo->video_caminho && Storage::exists($conteudo->video_caminho), 404);

        return response()->file(Storage::path($conteudo->video_caminho), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function capa(Conteudo $conteudo): BinaryFileResponse
    {
        abort_unless($conteudo->capa_caminho && Storage::exists($conteudo->capa_caminho), 404);

        return response()->file(Storage::path($conteudo->capa_caminho), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Postagem falhou: volta a "agendado" e posta na próxima rodada (se o horário já passou, em até 5 minutos). */
    public function tentarDeNovo(Conteudo $conteudo): RedirectResponse
    {
        if ($conteudo->estado !== 'falhou') {
            return back();
        }
        // Horário já passou: vira "agora", para entrar na janela de postagem.
        $conteudo->forceFill(['estado' => 'agendado', 'erro_postagem' => null,
            'agendado_para' => $conteudo->agendado_para && $conteudo->agendado_para->isFuture() ? $conteudo->agendado_para : now()])->save();

        return back()->with('ok', 'Combinado: a Central tenta postar de novo nos próximos 5 minutos.');
    }

    public function lamina(Conteudo $conteudo, ConteudoLamina $lamina): StreamedResponse
    {
        abort_unless($lamina->conteudo_id === $conteudo->id && Storage::exists($lamina->caminho), 404);

        return Storage::response($lamina->caminho, null, ['Cache-Control' => 'private, max-age=86400', 'Content-Type' => 'image/jpeg']);
    }

    public function pdf(Conteudo $conteudo): StreamedResponse
    {
        abort_unless($conteudo->pdf_caminho && Storage::exists($conteudo->pdf_caminho), 404);

        return Storage::download($conteudo->pdf_caminho, $conteudo->nome.'-linkedin.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function decidir(Conteudo $conteudo, string $estado, string $decisao, ?string $nota = null): void
    {
        $conteudo->forceFill(['estado' => $estado, 'decisao' => $decisao, 'nota' => $nota,
            'versao_decidida' => $conteudo->versao, 'decidido_em' => now()])->save();
    }

    /** Abriu um carrossel de outra marca (link do aviso, por exemplo): a marca do topo acompanha. */
    private function abrirMarcaDo(Request $request, Conteudo $conteudo): void
    {
        if ($request->attributes->get('marca')?->id !== $conteudo->marca_id) {
            $request->session()->put('marca', $conteudo->marca->slug);
        }
    }
}
