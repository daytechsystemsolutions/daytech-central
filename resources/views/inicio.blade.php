<x-layouts.app titulo="Início">
  <span class="data">{{ ucfirst(now()->locale('pt_BR')->translatedFormat('l, j \d\e F')) }}</span>

  <section class="vazio cartao" aria-labelledby="nada-esperando">
    <span class="circulo"><x-icone nome="certo" tamanho="30"/></span>
    <h1 id="nada-esperando" class="secao-titulo">Nada esperando você</h1>
    <p>Quando a produção de quinta terminar, os carrosséis de {{ $marca?->nome }} aparecem aqui para você aprovar.</p>
  </section>

  @if($marca)
    <section class="secao" aria-labelledby="conta">
      <h2 id="conta" class="secao-titulo">Instagram de {{ $marca->nome }}</h2>
      <div class="cartao">
        <div class="linha-item">
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
