<x-layouts.app titulo="Resultado do post">
  @php
    [$faixa, $classe] = $post->faixa();
    $partes = $post->nota_partes['partes'] ?? [];
    $nomes = ['alcance' => 'Alcance', 'valor' => 'Valor (salvos + compartilhados por 100 alcançados)', 'crescimento' => 'Crescimento (seguidores + visitas por 100)', 'interesse' => 'Interesse comercial (ações no perfil por 100)', 'retencao' => 'Retenção (segundos assistidos em média)'];
    $numero = fn ($v) => $v === null ? '—' : number_format($v, 0, ',', '.');
    $taxa = fn ($v) => $v === null ? '—' : str_replace('.', ',', (string) round($v, 1));
  @endphp
  <div class="cabeca-aprovar">
    <div class="secao">
      <span class="sobretitulo">Resultado</span>
      <h1 class="titulo-pagina">{{ $post->titulo() }}</h1>
      <span class="fraco">{{ ucfirst($post->publicado_em->locale('pt_BR')->translatedFormat('l, d/m/Y')) }} às {{ $post->publicado_em->format('H\hi') }}
        · {{ ['CAROUSEL_ALBUM' => 'carrossel', 'VIDEO' => 'vídeo', 'IMAGE' => 'imagem'][$post->tipo_midia] ?? $post->tipo_midia }}</span>
    </div>
    <a class="icone-botao" href="{{ route('resultados') }}" aria-label="Voltar para Resultados"><x-icone nome="voltar" tamanho="18"/></a>
  </div>

  <div class="aprovar-grade">
    <div class="aprovar-lado">
      <section class="cartao forte cartao-corpo nota-cartao">
        <div class="titulo-com-botao">
          <span class="rotulo">Nota do post</span>
          @if($post->permalink)<a class="forte" href="{{ $post->permalink }}" target="_blank" rel="noopener">Ver no Instagram</a>@endif
        </div>
        <div class="nota-linha">
          <strong class="nota-enorme">{{ $post->nota ?? '—' }}</strong>
          <span class="pilula {{ $classe }}">{{ $faixa }}{{ $post->nota !== null && ! $post->nota_fechada_em ? ' · parcial até o 7º dia' : '' }}</span>
        </div>
        @foreach($partes as $nome => $parte)
          <div class="barra-parte">
            <div class="titulo-com-botao"><span class="fraco">{{ $nomes[$nome] ?? $nome }}</span><span class="forte">{{ $parte['pontos'] }} pts</span></div>
            <div class="barra"><span class="barra-{{ $parte['pontos'] >= 70 ? 'alta' : ($parte['pontos'] >= 50 ? 'media' : 'baixa') }} largura-{{ (int) (round($parte['pontos'] / 10) * 10) }}"></span></div>
            <span class="fraco pequeno">este post: {{ $nome === 'alcance' ? $numero($parte['valor']) : $taxa($parte['valor']) }} · média da conta: {{ $parte['media'] === null ? 'sem histórico' : ($nome === 'alcance' ? $numero($parte['media']) : $taxa($parte['media'])) }} · peso {{ $parte['peso'] }}%</span>
          </div>
        @endforeach
        @if($post->sem_numeros)<p class="fraco">O Instagram não tem os números deste post (ele é de antes da conta comercial).</p>@endif
      </section>

      @if($ultima)
        <section class="resumo" aria-label="Números do post">
          @foreach(['visualizacoes' => 'visualizações', 'alcance' => 'alcance', 'interacoes' => 'interações', 'curtidas' => 'curtidas', 'comentarios' => 'comentários', 'compartilhamentos' => 'compartilhados', 'salvos' => 'salvos', 'visitas_perfil' => 'visitas ao perfil', 'novos_seguidores' => 'novos seguidores'] as $col => $rotulo)
            <div class="cartao indicador"><strong>{{ $numero($ultima->{$col}) }}</strong><span class="fraco">{{ $rotulo }}</span></div>
          @endforeach
        </section>
        <p class="fraco pequeno">Números acumulados até {{ $ultima->dia->format('d/m') }}. "—" é número que o Instagram não entrega para este tipo de post.</p>
      @endif
    </div>

    <div class="aprovar-lado">
      <section class="secao">
        <h2 class="secao-titulo">Como evoluiu.</h2>
        <div class="evolucao">
          @foreach($evolucao as $e)
            <div class="cartao indicador">
              <span class="fraco">{{ $e['quando'] }}</span>
              <strong>{{ $e['leitura'] ? $numero($e['leitura']->visualizacoes) : '—' }}</strong>
              <span class="fraco pequeno">{{ $e['leitura'] ? 'visualizações' : (isset($e['data']) && $e['data']->isFuture() ? 'em '.$e['data']->format('d/m') : 'sem leitura') }}</span>
            </div>
          @endforeach
        </div>
      </section>

      <section class="secao">
        <h2 class="secao-titulo">Como foi feito.</h2>
        @if($post->como_foi_feito)
          <div class="linha-pilulas">
            @foreach(['pilar', 'publico_principal', 'formato', 'chamada'] as $campo)
              @if(! empty($post->como_foi_feito[$campo]))<span class="pilula agendado">{{ \App\Services\Resultados\Rotulos::de($post->como_foi_feito[$campo]) }}</span>@endif
            @endforeach
            @if(! empty($post->como_foi_feito['tema']))<span class="pilula neutro">tema: {{ $post->como_foi_feito['tema'] }}</span>@endif
            @if($post->como_foi_feito_estimado)<span class="pilula neutro">estimado pela IA</span>@endif
          </div>
        @else
          <p class="fraco">A IA classifica este post (pilar, público, formato, chamada final) na próxima sincronização.</p>
        @endif
      </section>

      <section class="destaque-espera">
        <span class="rotulo">Leitura do Claude</span>
        @if($post->leitura)
          <p class="texto-longo">{{ $post->leitura }}</p>
          <span class="fraco pequeno">Escrita em {{ $post->leitura_em->format('d/m') }}.</span>
        @else
          <p>A leitura (o que deu certo e o que testar no próximo) aparece aqui quando a nota fechar, no 7º dia do post.</p>
        @endif
      </section>

      <section class="secao">
        <h2 class="secao-titulo">Comentários ({{ $post->comentarios->count() }}).</h2>
        @forelse($post->comentarios as $c)
          <div class="cartao cartao-corpo">
            <span class="forte">{{ '@'.$c->usuario }} <span class="fraco">· {{ $c->comentado_em->locale('pt_BR')->diffForHumans() }}</span></span>
            <p>{{ $c->texto }}</p>
          </div>
        @empty
          <p class="fraco">Nenhum comentário ainda.</p>
        @endforelse
        @if($post->comentarios->count() && $post->permalink)<a class="botao" href="{{ $post->permalink }}" target="_blank" rel="noopener">Responder no Instagram</a>@endif
      </section>
    </div>
  </div>
</x-layouts.app>
