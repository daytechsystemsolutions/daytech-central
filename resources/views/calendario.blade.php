<x-layouts.app titulo="Calendário">
  <div class="mes-cabeca">
    <a class="icone-botao" href="{{ route('calendario', ['mes' => $mes->subMonth()->format('Y-m')]) }}" aria-label="Mês anterior"><x-icone nome="voltar" tamanho="18"/></a>
    <h1 class="titulo-pagina">{{ ucfirst($mes->locale('pt_BR')->translatedFormat('F Y')) }}</h1>
    <a class="icone-botao" href="{{ route('calendario', ['mes' => $mes->addMonth()->format('Y-m')]) }}" aria-label="Próximo mês"><x-icone nome="seta-direita" tamanho="18"/></a>
  </div>
  <p class="fraco">Toque num tema para trocar ou tirar antes da produção. A produção roda toda quinta e faz a semana seguinte.</p>

  @forelse($semanas as $inicioSemana => $itens)
    @php
      $ini = \Carbon\Carbon::parse($inicioSemana);
    @endphp
    <section class="secao">
      <h2 class="rotulo">Semana de {{ $ini->format('d/m') }} a {{ $ini->copy()->addDays(6)->format('d/m') }}</h2>
      @foreach($itens as $item)
        @php
          $conteudo = $item->carrossel ? ($conteudos[$item->carrossel] ?? null) : null;
          [$rotulo, $classe] = $item->situacao($conteudo);
        @endphp
        <details class="cartao item-cal">
          <summary class="item-cal-linha">
            <span class="dia"><span class="rotulo">{{ $item->data->locale('pt_BR')->translatedFormat('D') }}</span><strong>{{ $item->data->format('d') }}</strong></span>
            <span class="textos">
              <span class="forte {{ $rotulo === 'tirado' ? 'riscado' : '' }}">{{ $item->titulo }}</span>
              <span class="linha-pilulas"><span class="pilula {{ $classe }}">{{ $rotulo }}</span><span class="fraco">{{ \App\Models\Conteudo::publicoEmTexto($item->publico) }}</span></span>
            </span>
          </summary>
          <div class="cartao-corpo borda-topo">
            @if($item->ideia)<p class="fraco">{{ $item->ideia }}</p>@endif
            @if($item->decisao === 'trocar' && $item->nota)<p class="fraco">Troca pedida: {{ $item->nota }}</p>@endif

            @if($conteudo && $conteudo->estado === 'aguardando')
              <a class="botao escuro cheio" href="{{ route('aprovar.ver', $conteudo) }}">Ver e aprovar</a>
            @elseif($conteudo)
              <a class="botao cheio" href="{{ route('aprovar.ver', $conteudo) }}">Ver o carrossel</a>
            @elseif($item->podeMexer())
              @if($item->decisao === 'tirar' || $item->decisao === 'trocar')
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
                  <button type="submit" class="botao escuro cheio">Salvar troca</button>
                </form>
                <form method="post" action="{{ route('calendario.tirar', $item) }}">
                  @csrf
                  <button type="submit" class="botao cheio texto-erro">Tirar este tema</button>
                </form>
              @endif
            @endif
          </div>
        </details>
      @endforeach
    </section>
  @empty
    <section class="vazio cartao"><p>Nenhum tema neste mês.</p></section>
  @endforelse

  <section class="secao" aria-labelledby="ideias">
    <h2 id="ideias" class="secao-titulo">Ideias</h2>
    <p class="fraco">Anote aqui o que vier à cabeça. A produção de quinta olha esta lista antes de escolher temas novos.</p>
    <form method="post" action="{{ route('ideias.criar') }}" class="cartao cartao-corpo">
      @csrf
      <div class="campo">
        <label for="ideia-texto">Nova ideia</label>
        <textarea id="ideia-texto" name="texto" rows="2" required placeholder="Ex.: carrossel sobre a nova NR-1 e riscos psicossociais"></textarea>
        @error('texto')<span class="erro-campo">{{ $message }}</span>@enderror
      </div>
      <button type="submit" class="botao escuro">Guardar ideia</button>
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
