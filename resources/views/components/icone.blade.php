@props(['nome', 'tamanho' => 22])
<svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($nome)
    @case('inicio')<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>@break
    @case('aprovar')<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 12l3 3 5-6"/>@break
    @case('calendario')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>@break
    @case('publicados')<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>@break
    @case('marcas')<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>@break
    @case('seguranca')<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>@break
    @case('sair')<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>@break
    @case('seta-baixo')<path d="M6 9l6 6 6-6"/>@break
    @case('seta-direita')<path d="M5 12h14M13 6l6 6-6 6"/>@break
    @case('voltar')<path d="M15 6l-6 6 6 6"/>@break
    @case('certo')<path d="M5 12l5 5L20 7"/>@break
    @case('atencao')<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>@break
@endswitch
</svg>
