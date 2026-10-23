<x-layouts.app titulo="Resultados">
  @php
    $link = fn (array $q) => route('resultados', array_merge(['periodo' => $periodo->tipo, 'data' => $periodo->inicio->toDateString(), 'ordem' => $ordem], $q));
  @endphp
  <div class="secao">
    <div class="titulo-com-botao">
      <span class="sobretitulo">Instagram</span>
      <a class="forte" href="{{ route('resultados.aprendizados') }}">O que aprendemos</a>
    </div>
    <h1 class="titulo-pagina">Resultados <span class="marca-texto">{{ $periodo->tipo === 'semana' ? 'da semana.' : ($periodo->tipo === 'ano' ? 'de '.$periodo->inicio->format('Y').'.' : 'de '.$periodo->inicio->locale('pt_BR')->translatedFormat('F').'.') }}</span></h1>
  </div>

  <nav class="abas" aria-label="Período">
    @foreach(['semana' => 'Semana', 'mes' => 'Mês', 'ano' => 'Ano'] as $tipo => $nome)
      <a href="{{ route('resultados', ['periodo' => $tipo, 'ordem' => $ordem]) }}" @if($periodo->tipo === $tipo) aria-current="page" @endif>{{ $nome }}</a>
    @endforeach
  </nav>
  <div class="mes-cabeca">
    <a class="icone-botao" href="{{ $link(['data' => $periodo->anterior()->inicio->toDateString()]) }}" aria-label="Período anterior"><x-icone nome="voltar" tamanho="18"/></a>
    <span class="forte">{{ ucfirst($periodo->nome()) }}</span>
    @if($periodo->temSeguinte())
      <a class="icone-botao" href="{{ $link(['data' => $periodo->seguinte()->inicio->toDateString()]) }}" aria-label="Próximo período"><x-icone nome="seta-direita" tamanho="18"/></a>
    @else
      <span class="icone-botao apagado" aria-hidden="true"></span>
    @endif
  </div>

  <section class="resumo" aria-label="Resumo do período">
    @foreach($resumo as $r)
      <div class="cartao forte indicador">
        <strong>{{ number_format($r['valor'], 0, ',', '.') }}</strong>
        <span class="fraco">{{ $r['nome'] }}</span>
        @if($r['variacao'])<span class="variacao {{ str_starts_with($r['variacao'], '−') ? 'cai' : 'sobe' }}">{{ $r['variacao'] }}</span>@endif
      </div>
    @endforeach
  </section>
  <p class="fraco pequeno">Soma dos posts publicados no período, comparada com o período anterior.</p>

  @if($destaques->count())
    <section class="destaque-espera" aria-labelledby="destaques">
      <span class="rotulo">Os que mais engajaram</span>
      @foreach($destaques as $n => $d)
        <a class="destaque-linha" href="{{ route('resultados.post', $d['post']) }}">
          <span class="posicao">{{ $n + 1 }}</span>
          <span class="textos">
            <span class="forte">{{ $d['post']->titulo() }}</span>
            @if($d['motivo'])<span class="fraco">{{ $d['motivo'] }}</span>@endif
          </span>
          <span class="nota-grande">{{ $d['post']->nota }}</span>
        </a>
      @endforeach
    </section>
  @endif

  <section class="secao" aria-labelledby="todos">
    <div class="titulo-com-botao">
      <h2 id="todos" class="secao-titulo">Todos os posts.</h2>
      <nav class="abas pequenas" aria-label="Ordem">
        <a href="{{ $link(['ordem' => 'nota']) }}" @if($ordem === 'nota') aria-current="page" @endif>Melhor nota</a>
        <a href="{{ $link(['ordem' => 'recentes']) }}" @if($ordem === 'recentes') aria-current="page" @endif>Mais recentes</a>
      </nav>
    </div>
    @forelse($grupos as $nome => $lista)
      @if($nome !== '')<h3 class="rotulo">{{ ucfirst($nome) }}</h3>@endif
      @foreach($lista as $p)
        @php
          [$faixa, $classe] = $p->faixa();
        @endphp
        <a class="cartao item-aprovar" href="{{ route('resultados.post', $p) }}">
          @if($p->capa_caminho)<img class="capa" src="{{ route('resultados.capa', $p) }}" alt="" width="72" height="90" loading="lazy">@endif
          <span class="textos">
            <span class="forte">{{ $p->titulo() }}</span>
            <span class="fraco">{{ $p->publicado_em->locale('pt_BR')->translatedFormat('D d/m') }} · {{ $p->sem_numeros ? 'sem números no Instagram' : number_format($p->ultimaMetrica?->visualizacoes ?? 0, 0, ',', '.').' visualizações' }}</span>
          </span>
          <span class="nota-lado">
            <strong>{{ $p->nota ?? '—' }}</strong>
            <span class="pilula {{ $classe }}">{{ $faixa }}</span>
          </span>
        </a>
      @endforeach
    @empty
      <section class="vazio cartao"><p>Nenhum post publicado neste período.</p></section>
    @endforelse
  </section>
</x-layouts.app>
