<x-layouts.entrada titulo="Entrar">
  <form method="post" action="{{ route('login') }}">
    @csrf
    <div class="campo">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
      @error('email')<span class="erro-campo">{{ $message }}</span>@enderror
    </div>
    <div class="campo">
      <label for="password">Senha</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      @error('password')<span class="erro-campo">{{ $message }}</span>@enderror
    </div>
    <label class="marcar"><input type="checkbox" name="remember" value="1"> Manter conectado neste aparelho</label>
    <button type="submit" class="botao escuro cheio">Entrar</button>
  </form>
  <p class="fraco">Esqueceu a senha? Ela é trocada no servidor, com o comando <code>central:usuario</code>.</p>
</x-layouts.entrada>
