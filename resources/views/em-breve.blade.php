<x-layouts.app :titulo="$titulo">
  <h1 class="titulo-pagina">{{ $titulo }}</h1>
  <section class="vazio cartao">
    <span class="circulo"><x-icone nome="certo" tamanho="30"/></span>
    <p>{{ $texto }}</p>
  </section>
</x-layouts.app>
