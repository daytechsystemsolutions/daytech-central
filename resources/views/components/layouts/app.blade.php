@props(['titulo' => null])
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>{{ $titulo ?? 'Central' }} · Central DayTech</title>
<link rel="preload" href="/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/central.css?v=2">
</head>
<body>
<div class="app">
  <nav class="menu" aria-label="Menu">
    <div class="menu-marca">
      <span class="rotulo">Central DayTech</span>
      <span class="forte">{{ $marcaAtual?->nome }}</span>
    </div>
    <a href="{{ route('inicio') }}" @if(request()->routeIs('inicio')) aria-current="page" @endif><x-icone nome="inicio"/>Início</a>
    <a href="{{ route('aprovar') }}" @if(request()->routeIs('aprovar')) aria-current="page" @endif><x-icone nome="aprovar"/>Aprovar</a>
    <a href="{{ route('calendario') }}" @if(request()->routeIs('calendario')) aria-current="page" @endif><x-icone nome="calendario"/>Calendário</a>
    <a href="{{ route('publicados') }}" @if(request()->routeIs('publicados')) aria-current="page" @endif><x-icone nome="publicados"/>Publicados</a>
    <a class="so-lateral" href="{{ route('marcas') }}" @if(request()->routeIs('marcas')) aria-current="page" @endif><x-icone nome="marcas"/>Marcas</a>
    <a class="so-lateral" href="{{ route('seguranca') }}" @if(request()->routeIs('seguranca')) aria-current="page" @endif><x-icone nome="seguranca"/>Segurança</a>
  </nav>

  <div class="principal">
    <header class="topo">
      <div>
        <span class="rotulo">Central DayTech</span>
        @if($marcas->count() > 1)
          <details class="seletor">
            <summary>{{ $marcaAtual?->nome }} <x-icone nome="seta-baixo" tamanho="18"/></summary>
            <div class="seletor-lista">
              @foreach($marcas as $m)
                <form method="post" action="{{ route('marcas.escolher', $m) }}">
                  @csrf
                  <button type="submit" class="seletor-item {{ $m->is($marcaAtual) ? 'atual' : '' }}">
                    {{ $m->nome }}
                    @if($m->is($marcaAtual))<x-icone nome="certo" tamanho="18"/>@endif
                  </button>
                </form>
              @endforeach
            </div>
          </details>
        @else
          <div class="titulo-pagina">{{ $marcaAtual?->nome }}</div>
        @endif
      </div>
      <a class="icone-botao so-celular" href="{{ route('marcas') }}" aria-label="Marcas e segurança"><x-icone nome="marcas" tamanho="20"/></a>
    </header>

    <main class="conteudo">
      @if(session('ok'))<div class="aviso ok" role="status"><x-icone nome="certo" tamanho="20"/>{{ session('ok') }}</div>@endif
      @if(session('aviso'))<div class="aviso atencao" role="status"><x-icone nome="atencao" tamanho="20"/>{{ session('aviso') }}</div>@endif
      {{ $slot }}
    </main>
  </div>
</div>
</body>
</html>
