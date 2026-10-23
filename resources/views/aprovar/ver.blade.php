<x-layouts.app titulo="Para aprovar">
  @php
    [$rotulo, $classe] = $conteudo->situacao();
    $quando = $conteudo->agendado_para ? $conteudo->agendado_para->locale('pt_BR')->translatedFormat('l, d/m').' às '.$conteudo->agendado_para->format('H\hi') : null;
  @endphp

  <div class="cabeca-aprovar">
    <div class="secao">
      <span class="sobretitulo">Para aprovar @if($total > 1)· {{ $posicao }} de {{ $total }}@endif</span>
      <h1 class="titulo-pagina">{{ $conteudo->titulo }}</h1>
      <span class="fraco">{{ $laminas->count() }} lâminas · versão {{ $conteudo->versao }} · <span class="pilula {{ $classe }}">{{ $rotulo }}</span></span>
    </div>
    @if($anterior || $proximo)
      <nav class="setas" aria-label="Outros carrosséis">
        @if($anterior)<a class="icone-botao" href="{{ route('aprovar.ver', $anterior) }}" aria-label="Anterior"><x-icone nome="voltar" tamanho="18"/></a>@endif
        @if($proximo)<a class="icone-botao" href="{{ route('aprovar.ver', $proximo) }}" aria-label="Próximo"><x-icone nome="seta-direita" tamanho="18"/></a>@endif
      </nav>
    @endif
  </div>

  @if($conteudo->ajuste_feito && $conteudo->estado === 'aguardando')
    <div class="aviso atencao"><x-icone nome="atencao" tamanho="20"/><span>Versão nova, com o ajuste: {{ $conteudo->ajuste_feito }}</span></div>
  @endif

  <div class="aprovar-grade">
    <section class="laminas" aria-label="Lâminas">
      @foreach($laminas as $l)
        <a class="lamina" href="{{ route('aprovar.lamina', [$conteudo, $l, 'v' => substr((string) $conteudo->hash_laminas, 0, 12)]) }}" target="_blank" rel="noopener">
          <img src="{{ route('aprovar.lamina', [$conteudo, $l, 'v' => substr((string) $conteudo->hash_laminas, 0, 12)]) }}" alt="Lâmina {{ $l->ordem }} de {{ $laminas->count() }}" loading="lazy" width="1080" height="1350">
        </a>
      @endforeach
    </section>

    <aside class="aprovar-lado">
      <div class="cartao forte">
        <div class="linha-item primeira"><div class="textos"><span class="forte">{{ $quando ? ucfirst($quando) : 'Sem data no calendário' }}</span></div></div>
        <div class="linha-item"><div class="textos"><span class="fraco">Instagram (automático) · LinkedIn (você posta)</span></div></div>
        @if($conteudo->publico)
          <div class="linha-item"><div class="textos"><span class="fraco">Para: {{ $conteudo->publicoTexto() }}</span></div></div>
        @endif

        <div class="cartao-corpo acoes">
          @if($conteudo->estado === 'aguardando')
            <form method="post" action="{{ route('aprovar.aprovar', $conteudo) }}">
              @csrf
              <input type="hidden" name="versao" value="{{ $conteudo->versao }}">
              @if(! $conteudo->agendado_para || $conteudo->agendado_para->isPast())
                <div class="campo">
                  <label for="quando">Quando postar?</label>
                  <input id="quando" name="quando" type="datetime-local" required value="{{ old('quando') }}">
                  @error('quando')<span class="erro-campo">{{ $message }}</span>@enderror
                </div>
              @endif
              <button type="submit" class="botao verde cheio"><x-icone nome="certo" tamanho="20"/>Aprovar e agendar</button>
            </form>
            <div class="duas">
              <details class="dobra">
                <summary class="botao cheio">Pedir ajuste</summary>
                <form method="post" action="{{ route('aprovar.ajuste', $conteudo) }}" class="dobra-corpo">
                  @csrf
                  <div class="campo">
                    <label for="nota">O que precisa mudar?</label>
                    <textarea id="nota" name="nota" rows="3" required placeholder="Ex.: trocar a capa, deixar a lâmina 3 mais curta">{{ old('nota') }}</textarea>
                    @error('nota')<span class="erro-campo">{{ $message }}</span>@enderror
                  </div>
                  <button type="submit" class="botao escuro cheio">Enviar pedido</button>
                </form>
              </details>
              <details class="dobra">
                <summary class="botao cheio texto-erro">Tirar</summary>
                <form method="post" action="{{ route('aprovar.tirar', $conteudo) }}" class="dobra-corpo">
                  @csrf
                  <span class="forte">Tirar este post? Ele não vai ser publicado.</span>
                  <button type="submit" class="botao cheio vermelho">Sim, tirar</button>
                </form>
              </details>
            </div>
          @else
            @if($conteudo->estado === 'falhou')
              <div class="aviso erro"><x-icone nome="atencao" tamanho="20"/><span>Não foi publicado: {{ $conteudo->erro_postagem }}</span></div>
              <form method="post" action="{{ route('aprovar.tentar', $conteudo) }}">
                @csrf
                <button type="submit" class="botao verde cheio">Tentar de novo</button>
              </form>
            @endif
            @if($conteudo->estado === 'ajuste' && $conteudo->nota)
              <p class="fraco">Ajuste pedido: {{ $conteudo->nota }}</p>
            @endif
            @if($conteudo->estado === 'publicado' && $conteudo->link)
              <a class="botao cheio" href="{{ $conteudo->link }}" target="_blank" rel="noopener">Ver no Instagram</a>
            @endif
            @if($conteudo->podeDesfazer())
              <form method="post" action="{{ route('aprovar.desfazer', $conteudo) }}">
                @csrf
                <button type="submit" class="botao cheio">Desfazer</button>
              </form>
            @endif
            @if($proximo)
              <a class="botao escuro cheio" href="{{ route('aprovar.ver', $proximo) }}">Próximo</a>
            @else
              <a class="botao escuro cheio" href="{{ route('inicio') }}">Concluir</a>
            @endif
          @endif
        </div>
      </div>

      <section class="cartao cartao-corpo">
        <div class="titulo-com-botao">
          <span class="rotulo">Legenda do Instagram</span>
          <button type="button" class="botao pequeno" data-copiar="legenda-ig">Copiar</button>
        </div>
        <p id="legenda-ig" class="texto-longo">{{ $conteudo->legenda_instagram }}</p>
      </section>

      @if($conteudo->texto_linkedin)
        <section class="cartao cartao-corpo">
          <div class="titulo-com-botao">
            <span class="rotulo">Texto do LinkedIn</span>
            <button type="button" class="botao pequeno" data-copiar="texto-li">Copiar</button>
          </div>
          <details>
            <summary class="botao-link">Ver o texto</summary>
            <p id="texto-li" class="texto-longo">{{ $conteudo->texto_linkedin }}</p>
          </details>
          @if($conteudo->pdf_caminho)
            <a class="botao" href="{{ route('aprovar.pdf', $conteudo) }}">Baixar PDF para o LinkedIn</a>
          @endif
        </section>
      @endif

      @if($conteudo->conferencia || $conteudo->fontes)
        <section class="cartao cartao-corpo">
          <details>
            <summary class="botao-link">Fontes e conferência</summary>
            <ul class="lista-simples">
              @foreach(($conteudo->conferencia ?? []) as $c)<li>{{ $c }}</li>@endforeach
              @foreach(($conteudo->fontes ?? []) as $f)<li>{{ $f }}</li>@endforeach
            </ul>
          </details>
        </section>
      @endif
    </aside>
  </div>
</x-layouts.app>
