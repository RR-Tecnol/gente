# Ambiente Docker — SISGEP

## Serviços

| Serviço | Porta | Descrição |
|---|---|---|
| Nginx | `8081` | Proxy reverso (acesse `http://localhost:8081`) |
| PHP-FPM (`app`) | interno | Laravel rodando neste container |
| SQL Server (`sqlserver`) | `1433` | Banco de dados |

---

## ⚠️ Objetivo Final: Deploy em VPS Linux

O ambiente Docker foi projetado para ser idêntico em Windows (dev) e Linux (VPS).
**Regras para garantir a compatibilidade:**

- ✅ **Nunca usar caminhos Windows hardcoded** em código PHP (ex: `C:\...`)
- ✅ **Usar `DIRECTORY_SEPARATOR` ou `storage_path()` / `base_path()`** do Laravel
- ✅ **Migrations com `Schema::hasTable()` + `Schema::hasColumn()`** — são idempotentes e seguras em qualquer DB
- ✅ **Permissões de arquivo**: ao fazer deploy, rode `chmod -R 775 storage bootstrap/cache`
- ✅ **Variáveis de ambiente**: nunca commitar `.env` — usar `.env.example` + configurar manualmente no servidor
- ✅ **`APP_ENV=production` + `APP_DEBUG=false`** na VPS
- ✅ **Chave de aplicação**: rodar `php artisan key:generate` no primeiro deploy

### Checklist de Deploy VPS
```bash
# Na VPS, dentro do projeto
cp .env.example .env
# [editar .env com credenciais de produção]
php artisan key:generate
php artisan migrate --force

# ATENÇÃO: Os seeds nativos não preenchem as tabelas de negócio estruturais.
# Para evitar erros 500 no uso de módulos como Lotação, Ponto, Folha e Escala,
# é OBRIGATÓRIA a execução dos scripts de injeção de carga base na primeira instalação:
php seed_dados_base.php
php seed_funcionario.php

php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

---

## Comandos do dia a dia

```bash
# Subir tudo
docker compose up -d

# Ver logs em tempo real
docker compose logs -f app

# Entrar no container PHP
docker compose exec app bash

# Rodar migrations
docker compose exec app php artisan migrate --force

# Rodar seeders
docker compose exec app php artisan db:seed --force

# Limpar caches Laravel
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear

# Acessar SQL Server direto
docker compose exec sqlserver /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "Gente@2024" -No \
  -Q "USE gente; SELECT ..."
```

## Verificação Pós-Deploy (contagens esperadas)

```bash
docker compose exec sqlserver /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "Gente@2024" -No \
  -Q "USE gente;
      SELECT 'PERFIL',               COUNT(*) FROM PERFIL;
      SELECT 'APLICACAO',            COUNT(*) FROM APLICACAO;
      SELECT 'ACESSO',               COUNT(*) FROM ACESSO;
      SELECT 'TABELA_GENERICA',      COUNT(*) FROM TABELA_GENERICA;
      SELECT 'CONFIGURACAO_SISTEMA', COUNT(*) FROM CONFIGURACAO_SISTEMA;
      SELECT 'USUARIO',              COUNT(*) FROM USUARIO;"
```

| Tabela | Esperado |
|---|---|
| PERFIL | 15 |
| APLICACAO | 46 |
| ACESSO | 346 |
| TABELA_GENERICA | 110 |
| CONFIGURACAO_SISTEMA | 6 |
| USUARIO | ≥ 1 |

## Credenciais Padrão

| Campo | Valor |
|---|---|
| Login | `admin` |
| Senha | `admin123` |
| Senha armazenada como | MD5 (`0192023a7bbd73250516f069df18b500`) |
| Perfis | Desenvolvedor (1) + Administrador (2) |

> ⚠️ Trocar a senha após o primeiro acesso em produção!

## Módulo Ponto Eletrônico

Desativado por padrão. Para habilitar:
1. Logue como admin
2. Acesse `/configuracoes/`
3. Ative a chave `MODULO_PONTO_ATIVO`

Ou via SQL:
```sql
UPDATE CONFIGURACAO_SISTEMA SET CONFIG_VALOR = '1' WHERE CONFIG_CHAVE = 'MODULO_PONTO_ATIVO'
```
