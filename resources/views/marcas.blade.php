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
          <span class="fraco">{{ $m->postagem_automatica ? 'Ligado: a Central posta no horário o que você aprovou, mesmo com o computador desligado' : 'Desligado: nada é postado sozinho' }}</span>
        </div>
        @if($m->postagem_automatica)
          <form method="post" action="{{ route('marcas.postagem', $m) }}">
            @csrf
            <input type="hidden" name="ligar" value="0">
            <button type="submit" class="botao pequeno">Desligar</button>
          </form>
        @else
          <details class="dobra">
            <summary class="botao pequeno escuro">Ligar</summary>
            <form method="post" action="{{ route('marcas.postagem', $m) }}" class="dobra-corpo confirmar-postagem">
              @csrf
              <input type="hidden" name="ligar" value="1">
              <span class="fraco">A Central vai postar sozinha no Instagram, no horário, só os carrosséis e Reels em que você tocou "Aprovar e agendar". Dá para desligar quando quiser.</span>
              <button type="submit" class="botao verde cheio">Ligar a postagem</button>
            </form>
          </details>
        @endif
      </div>
      @endif

      <div class="linha-item ritmo">
        <div class="textos">
          <span class="forte">Ritmo da semana</span>
          <span class="fraco">Quanto a produção de quinta faz por semana.</span>
        </div>
        <form method="post" action="{{ route('marcas.ritmo', $m) }}" class="ritmo-form">
          @csrf
          <div class="duas">
            <div class="campo">
              <label for="carrosseis-{{ $m->slug }}">Carrosséis</label>
              <input id="carrosseis-{{ $m->slug }}" name="carrosseis_semana" type="number" min="0" max="7" required value="{{ $m->carrosseis_semana }}">
            </div>
            <div class="campo">
              <label for="reels-{{ $m->slug }}">Reels</label>
              <input id="reels-{{ $m->slug }}" name="reels_semana" type="number" min="0" max="7" required value="{{ $m->reels_semana }}">
            </div>
          </div>
          <button type="submit" class="botao pequeno escuro">Salvar</button>
        </form>
      </div>

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
