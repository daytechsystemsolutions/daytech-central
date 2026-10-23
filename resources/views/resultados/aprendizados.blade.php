<x-layouts.app titulo="O que aprendemos">
  <div class="cabeca-aprovar">
    <div class="secao">
      <span class="sobretitulo">Últimos 12 meses · {{ $total }} posts com nota</span>
      <h1 class="titulo-pagina">O que <span class="marca-texto">aprendemos.</span></h1>
    </div>
    <a class="icone-botao" href="{{ route('resultados') }}" aria-label="Voltar para Resultados"><x-icone nome="voltar" tamanho="18"/></a>
  </div>

  <section class="destaque-espera" aria-labelledby="regras">
    <span class="rotulo" id="regras">O que a produção segue agora</span>
    @forelse($regras as $r)
      <div class="regra {{ $r->ativa ? '' : 'desligada' }}">
        <span class="pilula {{ $r->tipo === 'evite' ? 'erro' : ($r->tipo === 'teste' ? 'atencao' : 'espera') }}">{{ ['faca' => 'fazer', 'evite' => 'evitar', 'teste' => 'testar'][$r->tipo] }}</span>
        <span class="textos">
          <span class="forte">{{ $r->texto }}</span>
          @if($r->evidencia)<span class="fraco">{{ $r->evidencia }}</span>@endif
        </span>
        <form method="post" action="{{ route('regras.alternar', $r) }}">
          @csrf
          <button type="submit" class="botao pequeno">{{ $r->ativa ? 'Desligar' : 'Ligar' }}</button>
        </form>
      </div>
    @empty
      <p>As regras aparecem aqui depois da primeira análise da quinta (o Claude lê os resultados e escreve o que funciona).</p>
    @endforelse
    <span class="fraco">{{ $vale ? 'Com '.$total.' posts com nota, as regras já valem.' : 'Com menos de 10 posts com nota, são só pistas: a IA sugere, mas não troca temas sozinha.' }}</span>
  </section>

  @foreach($blocos as $b)
    <section class="secao">
      <h2 class="secao-titulo">{{ $b['titulo'] }}.</h2>
      <div class="cartao cartao-corpo">
        @foreach($b['linhas'] as $l)
          <div class="barra-parte">
            <div class="titulo-com-botao"><span class="forte">{{ $l['nome'] }}</span><span class="forte">nota {{ $l['nota'] }} <span class="fraco">· {{ $l['posts'] }} {{ $l['posts'] === 1 ? 'post' : 'posts' }}</span></span></div>
            <div class="barra"><span class="barra-{{ $l['nota'] >= 70 ? 'alta' : ($l['nota'] >= 50 ? 'media' : 'baixa') }} largura-{{ (int) (round($l['nota'] / 10) * 10) }}"></span></div>
          </div>
        @endforeach
      </div>
    </section>
  @endforeach

  @if($sem_classificacao)
    <p class="fraco">Por pilar, público e chamada final: aparecem quando a IA classificar os posts (próxima etapa).</p>
  @endif

  <section class="secao">
    <h2 class="secao-titulo">Quem segue a conta.</h2>
    <p class="fraco">Cidades, idade e horários dos seguidores: o Instagram só libera a partir de 100 seguidores (hoje: {{ $seguidores ?? '—' }}).</p>
  </section>
</x-layouts.app>
