<x-layouts.app titulo="Publicados">
  <h1 class="titulo-pagina">Publicados</h1>

  @forelse($publicados as $p)
    <article class="cartao cartao-corpo">
      <div class="publicado-cabeca">
        @php
          $capa = $p->laminasAtuais()->first();
        @endphp
        @if($capa)<img class="capa" src="{{ route('aprovar.lamina', [$p, $capa]) }}" alt="" width="72" height="90">@endif
        <div class="textos">
          <span class="forte">{{ $p->titulo }}</span>
          <span class="fraco">{{ ucfirst($p->publicado_em ? $p->publicado_em->locale('pt_BR')->translatedFormat('l, d/m').' às '.$p->publicado_em->format('H\hi') : '') }}</span>
          <span class="pilula ok">publicado</span>
        </div>
      </div>
      @if($p->link)<a class="botao cheio" href="{{ $p->link }}" target="_blank" rel="noopener">Ver no Instagram</a>@endif
    </article>
  @empty
    <section class="vazio cartao"><p>Nenhum post publicado ainda.</p></section>
  @endforelse

  <p class="fraco">Os números de cada post (alcance, visualizações, salvos) aparecem aqui na próxima etapa.</p>
</x-layouts.app>
