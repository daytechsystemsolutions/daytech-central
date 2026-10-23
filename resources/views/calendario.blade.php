<x-layouts.app titulo="Calendário">
  <div class="mes-cabeca">
    <a class="icone-botao" href="{{ route('calendario', ['mes' => $mes->subMonth()->format('Y-m')]) }}" aria-label="Mês anterior"><x-icone nome="voltar" tamanho="18"/></a>
    <h1 class="titulo-pagina">{{ $mes->locale('pt_BR')->translatedFormat('F') }} <span class="marca-texto">{{ $mes->format('Y') }}.</span></h1>
    <a class="icone-botao" href="{{ route('calendario', ['mes' => $mes->addMonth()->format('Y-m')]) }}" aria-label="Próximo mês"><x-icone nome="seta-direita" tamanho="18"/></a>
  </div>
  <p class="fraco">Toque num tema para trocar ou tirar antes da produção. A produção roda toda quinta e faz a semana seguinte.</p>

  @foreach($semanas as $inicioSemana => $itens)
    @php
      $ini = \Carbon\CarbonImmutable::parse($inicioSemana);
      $fimSemana = $ini->addDays(6);
      $passou = $fimSemana->lt(today());
      $produziveis = $itens->filter(fn ($i) => $i->podeProduzir() && ! ($i->carrossel && isset($conteudos[$i->carrossel])));
      $semPedido = $produziveis->whereNull('producao_pedida_em');
      $diaMinimo = $ini->lt(today()) ? today()->toDateString() : $ini->toDateString();
    @endphp
    @continue($passou && $itens->isEmpty())
    <section class="secao semana" aria-label="Semana de {{ $ini->format('d/m') }} a {{ $fimSemana->format('d/m') }}">
      <div class="titulo-com-botao">
        <h2 class="rotulo">Semana de {{ $ini->format('d/m') }} a {{ $fimSemana->format('d/m') }}</h2>
        @if(! $passou && $semPedido->count())
          <form method="post" action="{{ route('calendario.produzir-semana', $ini->toDateString()) }}">
            @csrf
            <button type="submit" class="botao pequeno escuro">Produzir esta semana agora</button>
          </form>
        @elseif(! $passou && $produziveis->count())
          <span class="pilula agendado">produção pedida</span>
        @endif
      </div>

      @if($itens->isEmpty())
        <p class="fraco">Nenhum tema nesta semana.</p>
      @endif

      @foreach($itens as $item)
        @php
          $conteudo = $item->carrossel ? ($conteudos[$item->carrossel] ?? null) : null;
          [$rotulo, $classe] = $item->situacao($conteudo);
        @endphp
        <details class="cartao item-cal {{ $conteudo && $conteudo->estado === 'aguardando' ? 'forte' : '' }}">
          <summary class="item-cal-linha">
            <span class="dia"><span class="rotulo">{{ $item->data->locale('pt_BR')->translatedFormat('D') }}</span><strong>{{ $item->data->format('d') }}</strong></span>
            <span class="textos">
              <span class="forte {{ $rotulo === 'tirado' ? 'riscado' : '' }}">{{ $item->titulo }}</span>
              <span class="linha-pilulas"><span class="pilula {{ $classe }}">{{ $rotulo }}</span>
                @if($item->sugerido_ia)<span class="pilula selo-ia">sugerido pela IA</span>@endif
                @if($item->teste)<span class="pilula atencao">teste</span>@endif
                <span class="fraco">{{ \App\Models\Conteudo::publicoEmTexto($item->publico) }}</span></span>
            </span>
          </summary>
          <div class="cartao-corpo borda-topo">
            @if($item->motivo)<p class="motivo-ia"><strong>Por que a IA sugeriu:</strong> {{ $item->motivo }}</p>@endif
            @if($item->pauta_chave && isset($porques[$item->pauta_chave]))
              @include('calendario.porque', ['pauta' => $porques[$item->pauta_chave]])
            @endif
            @if($item->ideia)<p class="fraco">{{ $item->ideia }}</p>@endif
            @if($item->decisao === 'trocar' && $item->nota)<p class="fraco">Troca pedida: {{ $item->nota }}</p>@endif

            @if($conteudo && $conteudo->estado === 'aguardando')
              <a class="botao escuro cheio" href="{{ route('aprovar.ver', $conteudo) }}">Ver e aprovar</a>
            @elseif($conteudo)
              <a class="botao cheio" href="{{ route('aprovar.ver', $conteudo) }}">Ver o carrossel</a>
            @elseif($item->producao_pedida_em)
              <p class="fraco">
                @if($item->producao_iniciada_em)
                  Em produção desde {{ $item->producao_iniciada_em->format('H\hi') }}. Quando ficar pronto, aparece em Para aprovar.
                @else
                  Produção pedida às {{ $item->producao_pedida_em->format('H\hi') }}. Começa na próxima sincronização (até 1 hora).
                @endif
              </p>
              @if(! $item->producao_iniciada_em)
                <form method="post" action="{{ route('calendario.cancelar-producao', $item) }}">
                  @csrf
                  <button type="submit" class="botao cheio">Cancelar o pedido</button>
                </form>
              @endif
            @elseif($item->podeMexer())
              @if($item->decisao === 'tirar' || $item->estado === 'tirado')
                <form method="post" action="{{ route('calendario.manter', $item) }}">
                  @csrf
                  <button type="submit" class="botao cheio">Voltar ao calendário</button>
                </form>
              @else
                <form method="post" action="{{ route('calendario.produzir', $item) }}">
                  @csrf
                  <button type="submit" class="botao escuro cheio">Produzir só este agora</button>
                </form>
                @if($item->decisao === 'trocar')
                  <form method="post" action="{{ route('calendario.manter', $item) }}">
                    @csrf
                    <button type="submit" class="botao cheio">Voltar ao tema original</button>
                  </form>
                @else
                  <form method="post" action="{{ route('calendario.trocar', $item) }}" class="secao">
                    @csrf
                    <div class="campo">
                      <label for="troca-{{ $item->id }}">Trocar por qual tema?</label>
                      <input id="troca-{{ $item->id }}" name="nota" type="text" required placeholder="Ex.: prazos do eSocial em dezembro">
                    </div>
                    <button type="submit" class="botao cheio">Salvar troca</button>
                  </form>
                @endif
                <form method="post" action="{{ route('calendario.tirar', $item) }}">
                  @csrf
                  <button type="submit" class="botao cheio texto-erro">Tirar este tema</button>
                </form>
              @endif
            @endif
          </div>
        </details>
      @endforeach

      @if(! $passou)
        <details class="cartao novo-tema">
          <summary class="botao-link">+ Novo tema nesta semana</summary>
          <form method="post" action="{{ route('calendario.novo') }}" class="cartao-corpo borda-topo">
            @csrf
            <div class="duas">
              <div class="campo">
                <label for="data-{{ $inicioSemana }}">Dia</label>
                <input id="data-{{ $inicioSemana }}" name="data" type="date" required min="{{ $diaMinimo }}" max="{{ $fimSemana->toDateString() }}" value="{{ $diaMinimo }}">
              </div>
              <div class="campo">
                <label for="hora-{{ $inicioSemana }}">Hora</label>
                <input id="hora-{{ $inicioSemana }}" name="hora" type="time" value="11:40">
              </div>
            </div>
            <div class="campo">
              <label for="titulo-{{ $inicioSemana }}">Tema</label>
              <input id="titulo-{{ $inicioSemana }}" name="titulo" type="text" required placeholder="Ex.: 5 erros no PGR que a fiscalização encontra">
            </div>
            <div class="campo">
              <label for="ideia-{{ $inicioSemana }}">O que falar (opcional)</label>
              <textarea id="ideia-{{ $inicioSemana }}" name="ideia" rows="2" placeholder="Pontos que não podem faltar, exemplo, chamada"></textarea>
            </div>
            <fieldset class="publicos">
              <legend class="forte">Para quem</legend>
              @foreach(\App\Models\Conteudo::PUBLICOS as $valor => $nome)
                <label class="marcar"><input type="checkbox" name="publico[]" value="{{ $valor }}"> {{ ucfirst($nome) }}</label>
              @endforeach
            </fieldset>
            <label class="marcar"><input type="checkbox" name="produzir" value="1" checked> Produzir agora</label>
            <button type="submit" class="botao escuro cheio">Criar tema</button>
          </form>
        </details>
      @endif
    </section>
  @endforeach

  <section class="secao" aria-labelledby="banco-pautas">
    <h2 id="banco-pautas" class="secao-titulo">Banco de pautas</h2>
    <p class="fraco">Temas que a pesquisa de quinta achou e ainda não estão no calendário, do maior potencial para o menor. O potencial junta o que o mercado procura, o histórico da conta, os prazos legais e as dúvidas reais.</p>
    @if($pautas->isEmpty())
      <p class="fraco">Nenhuma pauta no banco. A próxima pesquisa roda na quinta.</p>
    @endif
    @foreach($pautas as $pauta)
      <details class="cartao item-cal">
        <summary class="item-cal-linha">
          <span class="potencial {{ $pauta->potencial >= 70 ? 'alto' : ($pauta->potencial >= 50 ? 'medio' : '') }}"><strong>{{ $pauta->potencial }}</strong><span class="rotulo">potencial</span></span>
          <span class="textos">
            <span class="forte">{{ $pauta->titulo }}</span>
            <span class="linha-pilulas">
              <span class="pilula neutro">{{ $pauta->formato === 'reels' ? 'Reels' : 'carrossel' }}</span>
              @if($pauta->prazo)<span class="pilula atencao">prazo {{ $pauta->prazo->format('d/m') }}</span>@endif
              <span class="fraco">{{ \App\Models\Conteudo::publicoEmTexto($pauta->publico ? [$pauta->publico] : []) }}</span>
            </span>
          </span>
        </summary>
        <div class="cartao-corpo borda-topo">
          @if($pauta->ideia)<p class="fraco">{{ $pauta->ideia }}</p>@endif
          @include('calendario.porque', ['pauta' => $pauta])
          <form method="post" action="{{ route('pautas.calendario', $pauta) }}" class="secao">
            @csrf
            <div class="duas">
              <div class="campo">
                <label for="pauta-data-{{ $pauta->id }}">Dia</label>
                <input id="pauta-data-{{ $pauta->id }}" name="data" type="date" required min="{{ today()->toDateString() }}">
              </div>
              <div class="campo">
                <label for="pauta-hora-{{ $pauta->id }}">Hora</label>
                <input id="pauta-hora-{{ $pauta->id }}" name="hora" type="time" value="11:40">
              </div>
            </div>
            <button type="submit" class="botao escuro cheio">Pôr no calendário</button>
          </form>
          <form method="post" action="{{ route('pautas.descartar', $pauta) }}">
            @csrf
            <button type="submit" class="botao cheio texto-erro">Descartar esta pauta</button>
          </form>
        </div>
      </details>
    @endforeach
  </section>

  <section class="secao" aria-labelledby="ideias">
    <h2 id="ideias" class="secao-titulo">Ideias</h2>
    <p class="fraco">Anote aqui o que vier à cabeça. A produção de quinta olha esta lista antes de escolher temas novos.</p>
    <form method="post" action="{{ route('ideias.criar') }}" class="destaque-espera">
      @csrf
      <div class="campo">
        <label for="ideia-texto">Nova ideia</label>
        <textarea id="ideia-texto" name="texto" rows="2" required placeholder="Ex.: carrossel sobre a nova NR-1 e riscos psicossociais"></textarea>
        @error('texto')<span class="erro-campo">{{ $message }}</span>@enderror
      </div>
      <button type="submit" class="botao">Guardar ideia</button>
    </form>
    @foreach($ideias as $ideia)
      <div class="cartao linha-item primeira">
        <div class="textos"><span>{{ $ideia->texto }}</span><span class="fraco">{{ $ideia->created_at->locale('pt_BR')->diffForHumans() }}</span></div>
        <form method="post" action="{{ route('ideias.apagar', $ideia) }}">
          @csrf
          @method('delete')
          <button type="submit" class="botao pequeno" aria-label="Apagar ideia">Apagar</button>
        </form>
      </div>
    @endforeach
  </section>
</x-layouts.app>
