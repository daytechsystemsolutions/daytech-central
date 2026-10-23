<x-layouts.app titulo="Início">
  <span class="data">{{ ucfirst(now()->locale('pt_BR')->translatedFormat('l, j \d\e F')) }}</span>

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
    <section class="secao" aria-labelledby="ultimo">
      <h2 id="ultimo" class="secao-titulo">Último publicado.</h2>
      <a class="cartao cartao-corpo link-cartao" href="{{ route('publicados') }}">
        <span class="forte">{{ $ultimo->titulo }}</span>
        <span class="fraco">{{ ucfirst($ultimo->publicado_em?->locale('pt_BR')->translatedFormat('l, d/m') ?? '') }}</span>
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
