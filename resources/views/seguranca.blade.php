<x-layouts.app titulo="Segurança">
  <span class="sobretitulo">Sua conta</span>
  <h1 class="titulo-pagina">Segurança.</h1>

  @if(! $confirmado && ! $pendente)
    <section class="cartao cartao-corpo">
      <h2 class="secao-titulo">Ative os 2 fatores para usar a Central</h2>
      <p>Além da senha, a Central vai pedir um código do seu celular. Isso protege o Instagram das marcas mesmo se alguém descobrir a senha.</p>
      <ol class="passos">
        <li>Instale um app autenticador no celular (Google Authenticator, Microsoft Authenticator ou outro).</li>
        <li>Toque em <strong>Começar</strong>, leia o QR code com o app e digite o código que aparecer.</li>
      </ol>
      <form method="post" action="{{ url('/user/two-factor-authentication') }}">
        @csrf
        <button type="submit" class="botao escuro cheio">Começar</button>
      </form>
    </section>
  @endif

  @if($pendente)
    <section class="cartao cartao-corpo">
      <h2 class="secao-titulo">Leia o QR code com o app autenticador</h2>
      <div class="qr">{!! $qr !!}</div>
      <details>
        <summary class="botao-link">Está no celular? Digite a chave no app</summary>
        <p class="fraco">No app autenticador, escolha "inserir chave de configuração" e digite:</p>
        <p class="chave">{{ $chave }}</p>
      </details>
      <form method="post" action="{{ url('/user/confirmed-two-factor-authentication') }}">
        @csrf
        <div class="campo">
          <label for="code">Código de 6 números que apareceu no app</label>
          <input id="code" name="code" class="codigo" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required autofocus>
          @error('code', 'confirmTwoFactorAuthentication')<span class="erro-campo">{{ $message }}</span>@enderror
        </div>
        <button type="submit" class="botao escuro cheio">Confirmar</button>
      </form>
    </section>
  @endif

  @if($confirmado)
    <div class="aviso ok" role="status"><x-icone nome="certo" tamanho="20"/>2 fatores ligado. A Central pede o código do celular a cada entrada.</div>

    @if(count($codigos))
      <section class="cartao cartao-corpo">
        <h2 class="secao-titulo">Guarde estes códigos de reserva</h2>
        <p>Cada um entra uma vez, se você perder o celular. Guarde num lugar seguro (gerenciador de senhas). Eles não aparecem de novo.</p>
        <ul class="codigos">
          @foreach($codigos as $codigo)<li>{{ $codigo }}</li>@endforeach
        </ul>
      </section>
    @else
      <section class="cartao cartao-corpo">
        <h2 class="secao-titulo">Códigos de reserva</h2>
        <p>Perdeu os códigos ou já usou algum? Gere novos: os antigos param de valer.</p>
        <form method="post" action="{{ url('/user/two-factor-recovery-codes') }}">
          @csrf
          <button type="submit" class="botao cheio">Gerar novos códigos</button>
        </form>
      </section>
    @endif
  @endif
</x-layouts.app>
