# P0 — Diagnóstico de conexão SQL Server (timeout / HYT00)

## Sintoma

- `SQLSTATE[HYT00] Login timeout expired` ao rodar `php artisan migrate` ou `gente:prontidao-certificar` sem `--skip-db`.
- Não indica bug de migração nem do comando de certificação: o PHP não consegue abrir o socket/TDS até o SQL Server no `DB_HOST`/`DB_PORT`.

## Comando rápido

Na raiz do projeto Laravel (`gente/`):

```bash
php artisan gente:db-ping --json
```

Saída `ok: false` com `config.host`/`config.port` confirma que o `.env` aponta para um destino inacessível da máquina atual.

## Checklist

1. **Docker Compose**  
   - `docker compose ps` (ou `docker ps`) — o serviço `sqlserver` deve estar `healthy` e a porta `1433` publicada no host se você usa `DB_HOST=127.0.0.1`.

2. **Host conforme o ambiente (erro comum)**  
   - **`php artisan` no host (Fedora, IDE, terminal local):** use `DB_HOST=127.0.0.1` quando o `docker-compose` publica `1433:1433`. O nome `sqlserver` **não** resolve para o container fora da rede Docker — timeout de login costuma ser esse caso.  
   - **`php artisan` dentro do container `app`:** `DB_HOST=sqlserver` (nome do serviço na rede Docker).

3. **Credenciais**  
   - `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` alinhados ao container (`docker-compose.yml` usa variáveis padrão se não definidas).

4. **Timeout de login**  
   - `DB_LOGIN_TIMEOUT` no `.env` (segundos). Após alterar, rode `php artisan config:clear` se usava `config:cache`.

5. **Certificação**  
   - Com DB no ar: `php artisan migrate --force` e `php artisan gente:prontidao-certificar --json` (sem `--skip-db`).

## Referências

- `gente/docker-compose.yml` — serviço `sqlserver` e mapeamento `1433:1433`.
- `gente/config/database.php` — `login_timeout` e conexão `sqlsrv`.
