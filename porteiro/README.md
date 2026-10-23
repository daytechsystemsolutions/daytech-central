# Porteiro da VPS do site

Caddy que recebe tudo nas portas 80/443 da VPS do site, cuida do HTTPS e encaminha cada domínio para o
container dele pela rede docker `porteiro`. No servidor fica em `~/porteiro` (usuário do deploy do site).

- `Caddyfile`: um bloco por domínio (a pasta toda é montada em `/etc/caddy`). Mudou? Copie para o servidor e rode
  `docker compose -f ~/porteiro/compose.yml exec caddy caddy reload --config /etc/caddy/Caddyfile`.
- `compose.yml`: o container `porteiro_caddy`. O volume `caddy_data` guarda os certificados: não apagar.
- O Caddy tem IP fixo `10.201.0.2` na rede `porteiro` (`docker network create --subnet 10.201.0.0/24 --ip-range 10.201.0.128/25 porteiro`):
  quem confia no `X-Forwarded-For` confia só nesse IP, não em qualquer container da rede.
- Quem está atrás dele precisa entrar na rede externa `porteiro` e não abrir portas na máquina
  (ver `docs/DEPLOY-HTTPS.md` no repo do site).

Teste local feito em 23/10/2026 (com certificados locais): HTTPS dos dois domínios, http → https,
IP real do visitante chegando ao site e `X-Forwarded-For` forjado descartado.
