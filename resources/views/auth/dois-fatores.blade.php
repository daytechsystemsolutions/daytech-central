<x-layouts.entrada titulo="Código de acesso">
  <p>Abra o app autenticador no celular e digite o código de 6 números da <strong>Central DayTech</strong>.</p>
  <form method="post" action="{{ url('/two-factor-challenge') }}">
    @csrf
    <div class="campo">
      <label for="code">Código</label>
      <input id="code" name="code" class="codigo" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus>
      @error('code')<span class="erro-campo">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="botao escuro cheio">Confirmar</button>
  </form>
  <details>
    <summary class="botao-link">Perdeu o celular? Use um código de reserva</summary>
    <form method="post" action="{{ url('/two-factor-challenge') }}">
      @csrf
      <div class="campo">
        <label for="recovery_code">Código de reserva</label>
        <input id="recovery_code" name="recovery_code" autocomplete="off">
        @error('recovery_code')<span class="erro-campo">{{ $message }}</span>@enderror
      </div>
      <button type="submit" class="botao cheio">Entrar com código de reserva</button>
    </form>
  </details>
</x-layouts.entrada>
