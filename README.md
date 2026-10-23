# Central DayTech

Painel interno da DayTech para ver, aprovar, agendar e medir o conteúdo de todas as marcas
(MedNext SST, depois LuPe Educa). Endereço: https://central.daytechsolutions.com.br — roda na VPS do site.

Plano aprovado em 23/10/2026: https://claude.ai/artifact/WGcHdMCjbZSWA2oo93RxVj

| Pasta | O quê |
|---|---|
| `porteiro/` | Caddy da VPS do site: portas 80/443, HTTPS automático e um bloco por domínio (etapa 0) |
| (etapa 1) | aplicativo Laravel da Central |
