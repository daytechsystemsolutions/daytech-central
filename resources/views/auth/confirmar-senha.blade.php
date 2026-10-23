<x-layouts.entrada titulo="Confirmar senha">
  <p>Por segurança, digite sua senha de novo para continuar.</p>
  <form method="post" action="{{ url('/user/confirm-password') }}">
    @csrf
    <div class="campo">
      <label for="password">Senha</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
      @error('password')<span class="erro-campo">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="botao escuro cheio">Continuar</button>
  </form>
</x-layouts.entrada>
