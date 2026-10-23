@props(['marca' => null])
@php
  $arquivo = $marca ? 'img/marcas/'.$marca->slug.'.png' : null;
@endphp
@if($arquivo && file_exists(public_path($arquivo)))
  <img class="logo-marca" src="/{{ $arquivo }}" alt="{{ $marca->nome }}" height="22">
@else
  <span class="titulo logo-texto">{{ $marca?->nome ?? 'Central DayTech' }}</span>
@endif
