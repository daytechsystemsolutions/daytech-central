<x-layouts.app titulo="Marcas">
  <div class="secao">
    <span class="sobretitulo">Central DayTech</span>
    <h1 class="titulo-pagina">Marcas.</h1>
    <p class="fraco">Cada marca tem o Instagram dela. A postagem automática liga quando a postagem pela Central estiver pronta.</p>
  </div>

  @foreach($marcas as $m)
    <section class="cartao {{ $m->instagramConectado() ? '' : 'pontilhado' }}" aria-labelledby="marca-{{ $m->slug }}">
      <div class="marca-cabeca">
        <span class="marca-sigla {{ $m->slug === 'mednext' ? 'cor-mednext' : '' }}">{{ $m->iniciais }}</span>
        <div class="textos">
          <h2 id="marca-{{ $m->slug }}" class="secao-titulo">{{ $m->nome }}</h2>
          @if($m->descricao)<span class="fraco">{{ $m->descricao }}</span>@endif
        </div>
      </div>

      <div class="linha-item">
        <div class="textos">
          <span class="forte">Instagram</span>
          <span class="fraco">{{ $m->instagram_usuario ? '@'.$m->instagram_usuario : 'Ainda sem conta conectada. A conexão é feita no servidor.' }}</span>
          @if($m->instagram_erro)<span class="erro-campo">{{ $m->instagram_erro }}</span>@endif
        </div>
        @if($m->instagram_erro)
          <span class="pilula erro">com problema</span>
        @elseif($m->instagramConectado())
          <span class="pilula ok"><x-icone nome="certo" tamanho="14"/>conectado</span>
        @else
          <span class="pilula neutro">não conectado</span>
        @endif
      </div>

      @if($m->instagram_conta_id)
        <div class="linha-item">
          <div class="textos">
            <span class="forte">Conferir conexão</span>
            <span class="fraco">Pergunta ao Instagram se a conta continua ligada.</span>
          </div>
          <form method="post" action="{{ route('marcas.conferir', $m) }}">
            @csrf
            <button type="submit" class="botao">Conferir</button>
          </form>
        </div>
      @endif

      @if($m->instagramConectado())
      <div class="linha-item">
        <div class="textos">
          <span class="forte">Postar sozinho no horário</span>
          <span class="fraco">{{ $m->postagem_automatica ? 'Ligado: posta o que você aprovou' : 'Desligado: nada é postado sozinho' }}</span>
        </div>
        <span class="pilula {{ $m->postagem_automatica ? 'ok' : 'neutro' }}">{{ $m->postagem_automatica ? 'ligado' : 'desligado' }}</span>
      </div>
      @endif

      @if($m->horario_padrao)
        <div class="linha-item">
          <div class="textos">
            <span class="forte">Horário padrão</span>
            <span class="fraco">{{ $m->horario_padrao }}</span>
          </div>
        </div>
      @endif
    </section>
  @endforeach

  <div class="secao so-celular-bloco">
    <a class="botao cheio" href="{{ route('seguranca') }}"><x-icone nome="seguranca" tamanho="18"/>Segurança e códigos de reserva</a>
    <form method="post" action="{{ route('logout') }}" class="sair">
      @csrf
      <button type="submit" class="botao cheio"><x-icone nome="sair" tamanho="18"/>Sair</button>
    </form>
  </div>
</x-layouts.app>
