<x-layouts.app titulo="Início">
  @if($indicadores)
    @php
      $i = $indicadores;
      $sinal = fn ($n) => $n === null ? null : ($n > 0 ? '+'.$n : ($n < 0 ? '−'.abs($n) : '='));
    @endphp
    <section class="secao" aria-labelledby="indicadores">
      <span class="sobretitulo">{{ ucfirst($i['mes']) }} {{ now()->format('Y') }}</span>
      <h1 id="indicadores" class="titulo-pagina">Sua conta <span class="marca-texto">no mês.</span></h1>
      <div class="indicadores">
        <div class="cartao forte indicador">
          <span class="fraco">Seguidores</span>
          <strong>{{ $i['seguidores'] ?? '—' }}</strong>
          <span class="variacao {{ $i['seguidores_variacao'] === null ? '' : ($i['seguidores_variacao'] < 0 ? 'cai' : 'sobe') }}">{{ $i['seguidores_variacao'] === null ? 'variação a partir da próxima semana' : $sinal($i['seguidores_variacao']).' na semana' }}</span>
        </div>
        <div class="cartao forte indicador">
          <span class="fraco">Posts</span>
          <strong>{{ $i['posts'] ?? '—' }}</strong>
          <span class="variacao">{{ $i['posts_no_mes'] }} em {{ $i['mes'] }}</span>
        </div>
        <div class="cartao forte indicador">
          <span class="fraco">Nota do mês</span>
          <strong>{{ $i['nota_mes'] ?? '—' }}</strong>
          <span class="variacao {{ $i['nota_variacao'] === null ? '' : ($i['nota_variacao'] < 0 ? 'cai' : 'sobe') }}">
            {{ $i['nota_mes'] === null ? 'sem posts com nota' : $i['nota_faixa'][0].($i['nota_variacao'] !== null ? ' · '.$sinal($i['nota_variacao']).' pontos' : '') }}
          </span>
        </div>
        <div class="cartao forte indicador">
          <span class="fraco">Alcance em 7 dias</span>
          <strong>{{ number_format($i['sete_dias']['alcance'], 0, ',', '.') }}</strong>
          <span class="variacao">pessoas alcançadas</span>
        </div>
      </div>
      <p class="sete-dias"><strong>{{ $i['sete_dias']['desde'] ? 'Desde '.$i['sete_dias']['desde'] : 'Últimos 7 dias' }}:</strong>
        {{ $i['sete_dias']['interacoes'] }} interações · {{ $i['sete_dias']['visitas'] }} visitas ao perfil · {{ $i['sete_dias']['toques'] }} toques no link da bio</p>
    </section>
  @else
    <span class="data">{{ ucfirst(now()->locale('pt_BR')->translatedFormat('l, j \d\e F')) }}</span>
  @endif

  @if($esperando->count())
    <section class="destaque-espera" aria-labelledby="esperando">
      <span class="rotulo">Para aprovar</span>
      <h1 id="esperando">{{ $esperando->count() === 1 ? '1 conteúdo espera você.' : $esperando->count().' conteúdos esperam você.' }}</h1>
      <p>{{ $esperando->pluck('titulo')->implode(' · ') }}</p>
      <a class="botao escuro cheio" href="{{ route('aprovar') }}">Ver e aprovar <x-icone nome="seta-direita" tamanho="18"/></a>
    </section>
  @else
    <section class="vazio cartao" aria-labelledby="nada-esperando">
      <span class="circulo"><x-icone nome="certo" tamanho="30"/></span>
      <h1 id="nada-esperando" class="secao-titulo">Nada esperando você.</h1>
      <p>Quando a produção de quinta terminar, os carrosséis de {{ $marca?->nome }} aparecem aqui para você aprovar.</p>
    </section>
  @endif

  @if($proximos->count())
    <section class="secao" aria-labelledby="proximos">
      <div class="titulo-com-botao">
        <h2 id="proximos" class="secao-titulo">Próximos posts.</h2>
        <a href="{{ route('calendario') }}" class="forte">Calendário</a>
      </div>
      @foreach($proximos as $item)
        @php
          [$rotulo, $classe] = $item->situacao($item->carrossel ? ($conteudos[$item->carrossel] ?? null) : null);
        @endphp
        <div class="cartao item-cal-linha">
          <span class="dia"><span class="rotulo">{{ $item->data->locale('pt_BR')->translatedFormat('D') }}</span><strong>{{ $item->data->format('d/m') }}</strong></span>
          <span class="textos"><span class="forte">{{ $item->titulo }}</span><span class="linha-pilulas"><span class="pilula {{ $classe }}">{{ $rotulo }}</span></span></span>
        </div>
      @endforeach
    </section>
  @endif

  @if($ultimo)
    @php
      [$faixa, $classeFaixa] = $ultimo->faixa();
    @endphp
    <section class="secao" aria-labelledby="ultimo">
      <div class="titulo-com-botao">
        <h2 id="ultimo" class="secao-titulo">Último publicado.</h2>
        <a href="{{ route('resultados') }}" class="forte">Resultados</a>
      </div>
      <a class="cartao item-aprovar" href="{{ route('resultados.post', $ultimo) }}">
        @if($ultimo->capa_caminho)<img class="capa" src="{{ route('resultados.capa', $ultimo) }}" alt="" width="72" height="90" loading="lazy">@endif
        <span class="textos">
          <span class="forte">{{ $ultimo->titulo() }}</span>
          <span class="fraco">{{ ucfirst($ultimo->publicado_em->locale('pt_BR')->translatedFormat('l, d/m')) }} · {{ number_format($ultimo->ultimaMetrica?->visualizacoes ?? 0, 0, ',', '.') }} visualizações</span>
          <span class="pilula {{ $classeFaixa }}">{{ $ultimo->nota !== null ? 'nota '.$ultimo->nota.' · '.$faixa : $faixa }}{{ $ultimo->nota_fechada_em ? '' : ' (parcial)' }}</span>
        </span>
        <x-icone nome="seta-direita" tamanho="18"/>
      </a>
    </section>
  @endif

  @if($falhas)
    <div class="aviso erro"><x-icone nome="atencao" tamanho="20"/>{{ $falhas }} {{ $falhas === 1 ? 'post falhou' : 'posts falharam' }} ao publicar.</div>
  @elseif($ultimo)
    <div class="aviso ok"><x-icone nome="certo" tamanho="20"/>Nenhuma falha de postagem</div>
  @endif

  @if($marca)
    <section class="secao" aria-labelledby="conta">
      <h2 id="conta" class="secao-titulo">Instagram de {{ $marca->nome }}</h2>
      <div class="cartao">
        <div class="linha-item primeira">
          <div class="textos">
            @if($marca->instagram_usuario)
              <span class="forte">{{ '@'.$marca->instagram_usuario }}</span>
              <span class="fraco">Conferido {{ $marca->instagram_verificado_em?->locale('pt_BR')->diffForHumans() }}</span>
            @else
              <span class="forte">Sem conta conectada</span>
              <span class="fraco">A conexão é feita no servidor.</span>
            @endif
          </div>
          @if($marca->instagram_erro)
            <span class="pilula erro">com problema</span>
          @elseif($marca->instagramConectado())
            <span class="pilula ok"><x-icone nome="certo" tamanho="14"/>conectado</span>
          @else
            <span class="pilula neutro">não conectado</span>
          @endif
        </div>
      </div>
    </section>
  @endif
</x-layouts.app>
