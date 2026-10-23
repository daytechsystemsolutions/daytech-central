{{-- "Por que este tema": o potencial da pesquisa de pauta, parte por parte, com as fontes. --}}
<div class="porque">
  <p class="forte">Por que este tema · potencial {{ $pauta->potencial }} de 100</p>
  <ul class="porque-partes">
    @foreach(\App\Models\Pauta::PESOS as $parte => $peso)
      @php
        $p = $pauta->partes[$parte] ?? null;
      @endphp
      <li>
        <span class="porque-nome">{{ \App\Models\Pauta::NOMES[$parte] }} <span class="fraco">({{ $peso }}%)</span></span>
        <span class="porque-pontos">{{ $p['pontos'] ?? 0 }}</span>
        @if(! empty($p['resumo']))<span class="fraco porque-resumo">{{ $p['resumo'] }}</span>@endif
      </li>
    @endforeach
  </ul>
  @if(! empty($pauta->fontes))
    <p class="rotulo">Fontes</p>
    <ul class="porque-fontes">
      @foreach($pauta->fontes as $f)
        <li><a href="{{ $f['url'] }}" target="_blank" rel="noopener noreferrer">{{ $f['titulo'] }}</a></li>
      @endforeach
    </ul>
  @endif
</div>
