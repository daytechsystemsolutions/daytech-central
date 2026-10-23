<x-layouts.app titulo="Para aprovar">
  <div class="secao">
    <span class="sobretitulo">Conteúdo</span>
    <h1 class="titulo-pagina">Para <span class="marca-texto">aprovar.</span></h1>
    @if($esperando)
      <p class="fraco">{{ $esperando === 1 ? '1 carrossel espera você.' : $esperando.' carrosséis esperam você.' }} Abra cada um, veja as lâminas e decida.</p>
    @endif
  </div>

  @if($semanas->isEmpty())
    <section class="vazio cartao">
      <span class="circulo"><x-icone nome="certo" tamanho="30"/></span>
      <h2 class="secao-titulo">Tudo em dia.</h2>
      @if($pedidos)
        <p>{{ $pedidos === 1 ? '1 tema está' : $pedidos.' temas estão' }} na fila de produção. Quando ficar pronto, aparece aqui e você recebe um aviso.</p>
      @else
        <p>Quer ver conteúdo antes da quinta? No Calendário, toque em <strong>Produzir esta semana agora</strong>.</p>
        <a class="botao escuro" href="{{ route('calendario') }}">Abrir o Calendário</a>
      @endif
    </section>
  @endif

  @foreach($semanas as $inicio => $lista)
    <section class="secao">
      <h2 class="rotulo">
        @if($inicio === 'sem-data')
          Sem data
        @else
          @php
            $ini = \Carbon\CarbonImmutable::parse($inicio);
          @endphp
          Semana de {{ $ini->format('d/m') }} a {{ $ini->addDays(6)->format('d/m') }}
        @endif
      </h2>
      @foreach($lista as $c)
        @php
          [$rotulo, $classe] = $c->situacao();
          $capa = $c->laminasAtuais()->first();
        @endphp
        <a class="cartao item-aprovar {{ $c->estado === 'aguardando' ? 'forte' : '' }}" href="{{ route('aprovar.ver', $c) }}">
          @if($capa)<img class="capa" src="{{ route('aprovar.lamina', [$c, $capa, 'v' => substr((string) $c->hash_laminas, 0, 12)]) }}" alt="" width="72" height="90" loading="lazy">@endif
          <span class="textos">
            <span class="forte">{{ $c->titulo }}</span>
            <span class="fraco">{{ $c->agendado_para ? ucfirst($c->agendado_para->locale('pt_BR')->translatedFormat('D d/m')).' às '.$c->agendado_para->format('H\hi') : 'sem data' }}</span>
            <span class="pilula {{ $classe }}">{{ $rotulo }}</span>
          </span>
          <x-icone nome="seta-direita" tamanho="18"/>
        </a>
      @endforeach
    </section>
  @endforeach
</x-layouts.app>
