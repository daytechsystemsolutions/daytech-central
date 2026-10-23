# Central DayTech

Painel interno da DayTech (só uso próprio) para ver, aprovar, agendar e medir o conteúdo das marcas
(MedNext SST, depois LuPe Educa). https://central.daytechsolutions.com.br, na VPS do site, atrás do porteiro.
Plano: https://claude.ai/artifact/WGcHdMCjbZSWA2oo93RxVj · Telas aprovadas: https://claude.ai/artifact/EBV24yNn8nAD6cRUDRV5hR

Responder ao usuário sempre em português (pt-BR).

## Regras que não se negociam

- **Nunca `git push` para a `main`**: push na main publica em produção (.github/workflows/deploy.yml). Quem faz é o usuário.
  Commit só quando ele pedir.
- **Nunca acessar a VPS** (ssh, containers), nem para leitura: entregar os comandos prontos para o usuário rodar.
- Tokens (Instagram etc.) nunca no chat nem em arquivo do repo: ficam criptografados no banco (`instagram_token`,
  cast `encrypted`) e entram pelo `central:marca-instagram` (pergunta sem mostrar, ou `--env-stdin`).
- Nada é postado sem o "Aprovar e agendar" do usuário naquele conteúdo.

## Como rodar

```bash
./dev up                  # http://localhost:8380 (Docker, PHP 8.4 + nginx, SQLite em storage/banco)
./dev test                # testes (banco em memória)
./dev artisan <comando>
```

- Sem cache de config no local (compose.dev.yml): o cache iria para a pasta montada e os testes usariam o banco local.
- Usuário: `php artisan central:usuario <email>` (senha perguntada; `--zerar-2fa` se perder o celular). Sem cadastro
  nem "esqueci a senha" na tela. 2 fatores obrigatório (middleware `dois.fatores`).
- Marcas: `central:preparar` cria as que faltam; `central:marca-instagram <slug>` liga o Instagram.
- Fábrica (kit `~/Documentos/mednext-videos`, `./video central ...`): API em `routes/api.php` com chave de
  `central:token-fabrica` (só o hash no banco). Carrossel decidido não aceita lâminas novas na mesma versão (409);
  a aprovação vai com `hash_laminas` e o postador do kit confere de novo na hora de postar.

## Estrutura

- Laravel 13 + Fortify (login, 2FA), Blade sem build (CSS em `public/css/central.css`, fonte em `public/fonts`).
- CSP sem inline: nada de `style="..."` nem `<script>` inline nas views.
- `porteiro/`: Caddy da VPS (portas 80/443). O deploy copia o `Caddyfile` para `~/porteiro` e recarrega.
- Só o porteiro (IP fixo 10.201.0.2) é proxy confiável (`bootstrap/app.php`).
