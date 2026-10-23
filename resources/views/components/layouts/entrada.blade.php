@props(['titulo' => 'Entrar'])
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>{{ $titulo }} · Central DayTech</title>
<link rel="preload" href="/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/central.css?v=2">
</head>
<body>
<main class="tela-entrada">
  <div class="caixa-entrada">
    <div class="marca-daytech">
      <span class="rotulo">DayTech</span>
      <strong>Central</strong>
    </div>
    {{ $slot }}
  </div>
</main>
</body>
</html>
