# PROMPT ANTYGRAVITY — FASE 6 GENTE v3 (Deploy PMSL Produção Real — Go-live)

> **Cole este prompt no Antygravity APENAS após Fase 4 (incluindo T4.8) ter sido auditada e aprovada.**
> Estimativa total: ~3h30 a 5h Antygravity (com auditoria intercalada).
> **CRÍTICO:** esta fase é **GO-LIVE de produção REAL** atendendo **22.000 servidores municipais**. ZERO atalhos.
> **NÃO É POC.** O sistema vai entrar em operação efetiva. Falha aqui = folha de pagamento parada para 22k pessoas.

---

## CONTEXTO DA FASE 6

A Fase 6 é o **deploy oficial de produção** do GENTE v3 / SISGEP no servidor da Prefeitura de São Luís/MA (PMSL/MA). Não é piloto, não é homologação — é **go-live real** com servidores municipais reais, folha de pagamento real, eSocial real (mesmo em ambiente de homologação governamental, ESOCIAL_AMBIENTE=2).

### Stack confirmado por Claude via MCP

- **Laravel 8.12** (composer.json: `laravel/framework: ^8.12`)
- **PHP 8.4-fpm** (Dockerfile oficial)
- **SQL Server 2019** (driver `sqlsrv` + `pdo_sqlsrv` PECL + msodbcsql18)
- **Frontend SPA Vue 3 + Vite** (projeto separado em `gente-v3/`)
- **Build assets antigo:** Laravel Mix (`laravel-mix: ^6.0.6`)
- **Queue:** `database` driver em prod (NÃO sync) — `Bus::batch` precisa de `job_batches` table que já existe
- **Schedule cron:** **OBRIGATÓRIO** em prod — esocial-processar-fila, gente-sentinela-integridade, gente-healthcheck dependem dele
- **Servidor produção:** Hostinger KVM 8 (8 vCPU / 32GB RAM / NVMe — confirmado por Ronaldo)

### Premissa de ambiente

- **Homologação (KVM 4):** já existe e está sendo atualizada nas Fases 1-5. Validações finais E2E ali.
- **Produção (KVM 8):** primeiro deploy. **Zero dados em produção até este passo.**

### O que mudou desde a primeira versão deste prompt

- **Não é Laravel 10**, é Laravel 8.12 (correção do contexto interno).
- DB driver oficial em prod confirmado `sqlsrv` (não pgsql/mysql).
- **T6.8 original (Schedule task de alertas) foi REMOVIDA.** Decisão revisada na Fase 4 T4.8: os endpoints HTTP `ferias/alerta-vencer` e `afastamento/alerta-expirar` foram MIGRADOS para `/api/v3/ferias/alerta-vencer` e `/api/v3/afastamentos/alerta-expirar` (commits `dee2323` + `a95c5bb`). Eles continuam sendo endpoints de leitura HTTP autenticados, não cron — então `app/Console/Kernel.php` NÃO precisa ser editado. Tarefas T6.9-T6.15 originais foram renumeradas para T6.8-T6.14.

---

## REGRAS CRÍTICAS DE EXECUÇÃO

1. **Trabalhar em ORDEM ESTRITA:** T6.1 → T6.2 → ... → T6.14. **NÃO PULAR ETAPAS.**
2. **PARAR e reportar** se QUALQUER validação falhar antes de seguir adiante.
3. **NUNCA** rodar `php artisan migrate:fresh`, `migrate:reset` ou `db:wipe` em produção. APENAS `migrate --force`.
4. **NUNCA** rodar seeders sem confirmação explícita por commit/aprovação Claude.
5. **Backup obrigatório ANTES** de qualquer migrate ou seeder.
6. **Esta fase NÃO cria commits no repositório.** Todas as tarefas são operacionais SSH/Docker no servidor de produção. O código já foi todo commitado nas Fases 1–5 + Fase 4. Único ponto que poderia gerar commit (Schedule de alertas) foi descartado pela decisão revisada da Fase 4 T4.8.
7. **`.env` produção JAMAIS é commitado.** Apenas `.env.example` no repo.
8. **Smoke test obrigatório** ao fim — se reprovar, ROLLBACK.
9. Se Antygravity NÃO TEM acesso SSH ao servidor, **transcrever os comandos** em formato copy-paste para Ronaldo executar manualmente. Reportar saída no return.

---

## T6.1 — Backup do banco de produção (CRÍTICO, ~5 min)

**SE o banco de produção JÁ TEM dados** (improvável neste primeiro deploy mas verificar): backup completo.
**SE o banco está VAZIO** (primeiro deploy): apenas confirmar que não há nada para perder.

### 6.1.A — Verificar estado atual

Conectar ao SQL Server de produção e rodar:

```sql
USE gente;
GO
SELECT
    name AS table_name,
    SUM(rows) AS total_rows
FROM sys.tables t
JOIN sys.partitions p ON t.object_id = p.object_id
WHERE p.index_id IN (0,1)
GROUP BY name
ORDER BY total_rows DESC;
GO
```

**Se retornar 0 rows em todas as tabelas:** banco vazio, pode pular 6.1.B e ir para T6.2.
**Se retornar tabelas com rows:** seguir 6.1.B obrigatoriamente.

### 6.1.B — Backup completo via SQL Server

```sql
BACKUP DATABASE gente
TO DISK = 'C:\Backup\gente_pre_deploy_2026-05-10.bak'
WITH FORMAT, COMPRESSION,
     NAME = 'GENTE pre-deploy Fase6',
     STATS = 10;
GO

-- Verificar que backup foi criado
RESTORE VERIFYONLY FROM DISK = 'C:\Backup\gente_pre_deploy_2026-05-10.bak';
GO
```

**Saída esperada:** `The backup set on file 1 is valid.`

**Validação adicional:** copiar o `.bak` para um disco fora do servidor (S3 / KVM 4 / disco externo). Backup que mora no mesmo servidor NÃO é backup.

```bash
# Exemplo via rsync (se SSH habilitado entre KVM 8 → KVM 4)
rsync -avz --progress C:\Backup\gente_pre_deploy_2026-05-10.bak \
    user@kvm4-homolog:/srv/backups/
```

**ATENÇÃO:** **NÃO PROSSEGUIR PARA T6.2 ANTES DE CONFIRMAR QUE O BACKUP ESTÁ EM 2 LUGARES DIFERENTES.**

---

## T6.2 — Validar PHP 8.4 + extensões SQL Server no servidor (~10 min)

**Objetivo:** confirmar que o runtime PHP do KVM 8 atende ao stack do GENTE v3.

### 6.2.A — Versão do PHP

```bash
php -v
```

**Saída esperada:** `PHP 8.4.X` (qualquer patch level). Se for `8.3.X` ou inferior, **PARAR** e instalar PHP 8.4 antes de seguir.

### 6.2.B — Extensões obrigatórias

```bash
php -m | grep -iE 'pdo_sqlsrv|sqlsrv|mbstring|bcmath|xml|zip|gd|json|openssl|tokenizer|fileinfo|ctype|curl'
```

**Saída esperada (todas as 12 linhas):**
```
bcmath
ctype
curl
fileinfo
gd
json
mbstring
openssl
pdo_sqlsrv
sqlsrv
tokenizer
xml
zip
```

**Se faltar `pdo_sqlsrv` ou `sqlsrv`:** instalar via PECL:

```bash
sudo pecl install pdo_sqlsrv sqlsrv
sudo bash -c 'echo "extension=pdo_sqlsrv.so" > /etc/php/8.4/cli/conf.d/30-pdo_sqlsrv.ini'
sudo bash -c 'echo "extension=sqlsrv.so" > /etc/php/8.4/cli/conf.d/30-sqlsrv.ini'
sudo bash -c 'echo "extension=pdo_sqlsrv.so" > /etc/php/8.4/fpm/conf.d/30-pdo_sqlsrv.ini'
sudo bash -c 'echo "extension=sqlsrv.so" > /etc/php/8.4/fpm/conf.d/30-sqlsrv.ini'
sudo systemctl restart php8.4-fpm
```

### 6.2.C — Driver Microsoft msodbcsql18

```bash
odbcinst -q -d
```

**Saída esperada:** `[ODBC Driver 18 for SQL Server]`

**Se faltar:** instalar do repo oficial Microsoft:

```bash
curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | sudo gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" | sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev
```

### 6.2.D — Composer + Node.js

```bash
composer --version
node -v
npm -v
```

**Esperado:**
- Composer ≥ 2.x
- Node.js ≥ 18.x (ideal 20.x para Vite)
- npm ≥ 9.x

**Reportar:** preencher tabela:
```
PHP version: ___
Extensões PHP missing: ___ (esperado: nenhuma)
ODBC Driver 18: ___ (esperado: instalado)
Composer: ___
Node: ___
npm: ___
```

**SE QUALQUER COISA FALHAR**, parar aqui.

---

## T6.3 — Configurar `.env` de produção (~15 min)

**Objetivo:** criar o arquivo `.env` na raiz do projeto no servidor de produção, com valores reais (não-default).

### 6.3.A — Copiar template

```bash
cd /var/www/gente
cp .env.example .env
```

### 6.3.B — Editar valores OBRIGATÓRIOS para produção

Editar `.env` com `nano /var/www/gente/.env` ou `vim`:

```ini
APP_NAME="GENTE PMSL"
APP_DESCRICAO="Gestão de Pessoas — Prefeitura de São Luís/MA"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://gente.saoluis.ma.gov.br

AMBIENTE=producao

LOG_CHANNEL=stack
LOG_LEVEL=warning

# PII / LGPD — gerar via openssl rand -hex 32
GENTE_PII_BLIND_SALT=__PREENCHER_HEX_64_CHARS__
GENTE_PII_CPF_ENCRYPTED=false
GENTE_PII_MODEL_HIDE_CPF=false

# Frente 2: HMAC desligado em prod inicial (ativar após estabilidade)
GENTE_REQUEST_SIGNATURE_ENABLED=false
GENTE_REQUEST_SIGNATURE_LEEWAY_MS=30000

# Frente 3: honeytokens habilitados, blocklist ativada
GENTE_HONEYTOKENS_ENABLED=true
GENTE_HONEY_BLOCKLIST_ENFORCE=true
GENTE_HONEY_BLOCKLIST_CANARY_24H=true
GENTE_HONEY_BLOCKLIST_ON_TOUCH=false

# Tenant Scope: middleware ativo, enforce DESLIGADO inicialmente (logs primeiro)
GENTE_TENANT_SCOPE_MIDDLEWARE=true
GENTE_TENANT_SCOPE_ENFORCE=false
GENTE_TENANT_SCOPE_LOG_CHANNEL=tenant_scope

# Frontend RBAC: desligado até assignments carregados
VITE_GENTE_RBAC_UI_STRICT=false

# ═══════════════════════════════════════════════════════════════
# BANCO DE DADOS — SQL Server PMSL
# ═══════════════════════════════════════════════════════════════
DB_CONNECTION=sqlsrv
DB_HOST=__IP_DO_SQL_SERVER_PMSL__
DB_PORT=1433
DB_DATABASE=gente
DB_USERNAME=__USER_PMSL__
DB_PASSWORD=__SENHA_PMSL__
DB_LOGIN_TIMEOUT=8

# ═══════════════════════════════════════════════════════════════
# QUEUE — DATABASE driver (precisa job_batches table)
# ═══════════════════════════════════════════════════════════════
BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

# ═══════════════════════════════════════════════════════════════
# eSocial em ambiente HOMOLOGAÇÃO governamental (NÃO produção real)
# Trocar para 1 só APÓS PMSL/MA aprovar e governo liberar
# ═══════════════════════════════════════════════════════════════
ESOCIAL_AMBIENTE=2
ESOCIAL_CNPJ_EMPREGADOR=06205244000149
ESOCIAL_TIPO_INSCRICAO=1
ESOCIAL_VERSAO_PROC=GENTE-v3
ESOCIAL_IND_RETIF_DEFAULT=1
ESOCIAL_MAX_RETRY=5

# Recaptcha v3 (SEC-PROD-02 — ativa em prod)
RECAPTCHA_SITE_KEY=__SITE_KEY__
RECAPTCHA_SECRET_KEY=__SECRET_KEY__

# Mail (PMSL pode ter SMTP próprio — ajustar)
MAIL_MAILER=smtp
MAIL_HOST=__SMTP_PMSL__
MAIL_PORT=587
MAIL_USERNAME=__SMTP_USER__
MAIL_PASSWORD=__SMTP_PASS__
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@gente.saoluis.ma.gov.br
MAIL_FROM_NAME="GENTE PMSL"

# P5-Standby: integrações externas (false = desligado em prod inicial)
P5_ESOCIAL_ENVIO=false
P5_CNAB_BANCARIO=false
P5_API_TERCEIRA_FOLHA=false
```

### 6.3.C — Gerar `APP_KEY` e `GENTE_PII_BLIND_SALT`

```bash
# APP_KEY (Laravel)
php artisan key:generate --force

# GENTE_PII_BLIND_SALT (HMAC SHA-256, 32 bytes hex)
openssl rand -hex 32
# Copiar saída e colocar em GENTE_PII_BLIND_SALT no .env
```

### 6.3.D — Permissões corretas

```bash
sudo chown www-data:www-data /var/www/gente/.env
sudo chmod 600 /var/www/gente/.env
```

`chmod 600` = só o owner lê/escreve. **CRÍTICO para LGPD** — `.env` tem credenciais.

### 6.3.E — Validação smoke

```bash
cd /var/www/gente
php artisan config:show app.env
php artisan config:show database.default
```

**Esperado:**
```
production
sqlsrv
```

**Reportar saída.**

---

## T6.4 — Composer install em modo produção (~10 min)

```bash
cd /var/www/gente

# Limpar caches antigos antes
rm -rf vendor bootstrap/cache/*.php

# Install otimizado
composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs \
    --prefer-dist
```

**Por que `--no-dev`:** evita instalar `phpunit`, `mockery`, `laravel-debugbar`, `nunomaduro/collision` em prod (desnecessário + bloat de imagem).

**Por que `--optimize-autoloader`:** gera `vendor/composer/autoload_classmap.php` resolvido em build-time (10-15% mais rápido).

**Por que `--no-scripts`:** evita executar `post-autoload-dump` que tenta `php artisan package:discover` antes do `.env` estar pronto.

**Por que `--ignore-platform-reqs`:** Dockerfile oficial usa essa flag por causa de `eltonwebnet/jasper-rdr` que pede `php ^7.3|^8.0`. Em PHP 8.4, `composer install` reclama; mas a lib funciona.

**Validação:**

```bash
ls -la vendor/composer/autoload_classmap.php
# Esperado: arquivo com 200KB+

composer dump-autoload --optimize --no-scripts --ignore-platform-reqs
# Saída esperada: "Generated optimized autoload files"
```

---

## T6.5 — Build dos assets Vue 3 SPA (~15-30 min)

**Contexto importante:** o GENTE v3 tem 2 frontends:
1. **Legado (Laravel Mix):** `webpack.mix.js` + `resources/js/` — admin views Blade
2. **SPA Vue 3 (Vite):** projeto separado em `gente-v3/` que builda para `public/v3/`

### 6.5.A — Build do legacy Mix (CSS/JS auth pages)

```bash
cd /var/www/gente
npm install --production=false
npm run prod
```

Esse passo gera `public/css/app.css`, `public/js/app.js`, `public/mix-manifest.json`.

**Validação:**
```bash
ls -la public/css/app.css public/js/app.js
# Ambos devem existir e ter > 0 bytes
```

### 6.5.B — Build do SPA Vue 3 (gente-v3)

**Confirmar primeiro com Ronaldo:** o projeto `gente-v3/` está no mesmo servidor ou é deploy separado?

**Caso A — projeto está em `/var/www/gente-v3` (mesmo servidor):**

```bash
cd /var/www/gente-v3
npm install
npm run build

# Copiar artefatos para o Laravel
mkdir -p /var/www/gente/public/v3
cp -r dist/* /var/www/gente/public/v3/
```

**Caso B — projeto está noutro servidor:** Ronaldo deve buildar lá e fazer `rsync` dos `dist/` para `/var/www/gente/public/v3/`.

**Validação após cópia:**

```bash
ls -la /var/www/gente/public/v3/
# Esperado: pasta com index.html, assets/ (com .js e .css hashados)
```

```bash
# Confirmar que o template app.blade.php aponta para os assets correctos
grep -E 'v3/assets|v3/index' /var/www/gente/resources/views/v3/app.blade.php
```

### 6.5.C — Permissões finais

```bash
sudo chown -R www-data:www-data /var/www/gente/public
sudo chmod -R 755 /var/www/gente/public
```

---

## T6.6 — Migrations: rodar `migrate --force` (~5-15 min)

**ATENÇÃO MÁXIMA.** Esta é a tarefa mais perigosa. Um erro aqui pode corromper o schema. **NÃO TEM ROLLBACK FÁCIL.**

### 6.6.A — Verificar status atual

```bash
cd /var/www/gente
php artisan migrate:status
```

**Caso A — banco vazio:** vai mostrar todas as ~150 migrations como pendentes (`Pending`).
**Caso B — banco já tem schema:** vai mostrar quais rodaram (`Ran`) e quais faltam.

**Reportar saída.** Se houver alguma migration `Pending` que NÃO está na lista esperada (lista no docs/SPRINT_EXECUCAO_V3.md), **PARAR e reportar** — pode haver migration de outro branch acidentalmente incluída.

### 6.6.B — Smoke test de conexão

```bash
php artisan tinker
# No prompt:
> DB::connection()->getPdo();
> DB::select('SELECT @@VERSION as v')[0]->v;
> exit
```

**Esperado:** `Microsoft SQL Server 2019` (ou versão mais nova).

### 6.6.C — Backup IMEDIATO antes do migrate

**Mesmo já tendo backup da T6.1**, fazer backup novo aqui (estado pode ter mudado se T6.3 modificou alguma config):

```sql
USE gente;
GO
BACKUP DATABASE gente
TO DISK = 'C:\Backup\gente_pre_migrate_AAAAMMDD_HHMMSS.bak'
WITH FORMAT, COMPRESSION, NAME = 'GENTE pre-migrate Fase6.6', STATS = 10;
GO
```

### 6.6.D — Migrate force

```bash
cd /var/www/gente
php artisan migrate --force --no-interaction
```

**`--force`:** Laravel 8 exige isso em prod (sem flag, recusa rodar se `APP_ENV=production`).
**`--no-interaction`:** evita travar em prompt interativo.

**Saída esperada:** lista linha-a-linha de cada migration `Migrating: ...` → `Migrated: ...` em verde.

**SE HOUVER ERRO:**
- **PARAR IMEDIATAMENTE.** Não tentar `migrate:rollback` automaticamente.
- Copiar a saída completa do erro.
- Reportar a Claude com a stack trace.
- Aguardar instrução manual.

### 6.6.E — Validação final

```bash
php artisan migrate:status | grep -i pending
# Esperado: 0 linhas (nada pendente)
```

```sql
-- No SQL Server:
USE gente;
GO
SELECT COUNT(*) AS total_tabelas FROM sys.tables;
GO
-- Esperado: 200+ tabelas
```

---

## T6.7 — Seeders críticos para go-live (~10 min)

**REGRA DE OURO:** seeders DESTRUTIVOS NÃO RODAM em prod. Os seeders abaixo são todos **idempotentes** (verificam existência antes de inserir).

### Lista de seeders OBRIGATÓRIOS para o go-live

```bash
cd /var/www/gente

# 1. RUBRICA — catálogo PCCV oficial PMSL (Lei Municipal nº 4.749/2007 + atualizações)
php artisan db:seed --class=RubricasCatalogoSeeder --force

# 2. EVENTO — eventos base que o ContraChequeService consome
php artisan db:seed --class=EventosBaseSeeder --force

# 3. RUBRICA HE/Plantão — códigos 030/031/032 (necessário para GAP-MF-04 da Fase 2-A)
php artisan db:seed --class=RubricasHePlantaoSeeder --force

# 4. PCCV_DOMINIO — domínio canônico de carreiras
php artisan db:seed --class=PccvDominioSeeder --force

# 5. SECRETARIAS — estrutura organizacional PMSL inicial
php artisan db:seed --class=SecretariasSeed --force

# 6. PERFIL — papéis RBAC
php artisan db:seed --class=PerfilSeeder --force

# 7. RBAC matrix — assignments base (admin / gestor / servidor)
php artisan db:seed --class=RbacMatrixSeeder --force

# 8. TIPO_DOCUMENTO — RG, CPF, CNH, Título, etc.
php artisan db:seed --class=TipoDocumentoSeeder --force

# 9. Feriados nacionais e municipais 2026
php artisan db:seed --class=Feriados2026Seeder --force

# 10. Tabela genérica (sexo, raça, estado civil, escolaridade — usados pelo eSocial)
php artisan db:seed --class=TabelaGenericaSeeder --force

# 11. Configuração do sistema (flags MODULO_PONTO_ATIVO, etc.)
php artisan db:seed --class=ConfiguracaoSistemaSeeder --force

# 12. Honeytokens (Frente 3 segurança)
php artisan db:seed --class=HoneytokenSeeder --force
```

### Seeders que NÃO devem rodar em produção PMSL

```
❌ DaviSupremoSeeder              — usuário fundador hardcoded
❌ TestPersonasSeeder             — usuários de teste
❌ FuncionariosPMSLzSeeder        — funcionários DEMO (PMSL real importará via SISFOLHA)
❌ UsuariosPMSLzSeeder            — usuários DEMO
❌ EscalaFevereiroSeeder          — dados demo de escala
❌ FevereiroDemoSeeder            — qualquer coisa "Demo"
❌ SuperSeederEstresseMigracao    — só para load testing
❌ AuditorSemadHomologSeeder      — só homolog (gated por env GENTE_SEED_AUDITOR_SEMAD_STANDALONE)
```

### Validação após seeders

```sql
USE gente;
GO

-- Rubrica catálogo (esperado 60+ rubricas)
SELECT COUNT(*) FROM RUBRICA;

-- Eventos base (esperado 7+)
SELECT COUNT(*) FROM EVENTO;

-- Rubricas HE/Plantão específicas (códigos 030, 031, 032)
SELECT RUBRICA_CODIGO, RUBRICA_DESCRICAO FROM RUBRICA
WHERE RUBRICA_CODIGO IN ('030','031','032');
-- Esperado: 3 linhas

-- Configuração sistema
SELECT COUNT(*) FROM CONFIGURACAO_SISTEMA;
-- Esperado: 5+

-- Tipos de documento
SELECT COUNT(*) FROM TIPO_DOCUMENTO;
-- Esperado: 10+
GO
```

**Reportar todas as contagens.**

---

## ⏭️ T6.8 — REMOVIDA (não há ação necessária)

**A T6.8 original adicionava Schedule tasks `gente-ferias-alerta-vencer` e `gente-afastamento-alerta-expirar` no `app/Console/Kernel.php` para substituir endpoints HTTP que seriam removidos pela Fase 4 T4.8.**

**Decisão revisada após auditoria Claude (08/05/2026):** os endpoints `ferias/alerta-vencer` e `afastamento/alerta-expirar` NÃO eram cron jobs — eram endpoints de leitura HTTP user-facing (com filtro `Auth::user()` por perfil `COORD_DE_SETOR`). Foram MIGRADOS para `/api/v3/ferias/alerta-vencer` e `/api/v3/afastamentos/alerta-expirar` na Fase 4 T4.8 (commits `dee2323` + `a95c5bb`).

**Nada a fazer aqui.** O `app/Console/Kernel.php` já tem os Schedule tasks corretos (esocial-processar-fila, gente-sentinela-integridade, gente-healthcheck, etc.) que serão ativados pelo cron do servidor na T6.8 (renumerada).

Validação opcional (Antygravity pode pular): confirmar que os Schedule tasks NÃO incluem `alerta-vencer/expirar`:

```bash
cd /var/www/gente
php artisan schedule:list 2>/dev/null | grep -iE 'alerta-vencer|alerta-expirar'
# Esperado: 0 ocorrências
```

E confirmar que os endpoints novos `/api/v3/` estão registrados (T6.12 vai testar isso de qualquer forma):

```bash
php artisan route:list 2>/dev/null | grep -iE 'alerta-vencer|alerta-expirar'
# Esperado: 2 linhas com prefixo /api/v3/
```

**Prosseguir para T6.8 (cron Laravel — antes era T6.9).**

---

## T6.8 — Configurar cron Laravel no servidor (~5 min)

**Objetivo:** sem cron, **NENHUM** Schedule task roda. eSocial fila para. Healthcheck para. Limpeza diária para.

### 6.8.A — Editar crontab do user `www-data`

```bash
sudo crontab -u www-data -e
```

**Adicionar linha:**

```cron
* * * * * cd /var/www/gente && /usr/bin/php artisan schedule:run >> /var/www/gente/storage/logs/scheduler.log 2>&1
```

**Explicação:**
- `* * * * *` = roda a cada minuto (Laravel internamente decide qual task disparar)
- `>> .../scheduler.log` = appenda saída em arquivo de log auditável
- `2>&1` = junta stderr no stdout

### 6.8.B — Validar que cron está rodando

```bash
sudo systemctl status cron
# Esperado: "active (running)"
```

```bash
# Esperar 1 minuto e ver se o log foi tocado
sleep 65
ls -la /var/www/gente/storage/logs/scheduler.log
# Esperado: arquivo existe com algum tamanho > 0
```

```bash
# Forçar 1 execução manual para confirmar
sudo -u www-data php /var/www/gente/artisan schedule:run
# Esperado: linhas mostrando tasks disparadas (ou "No scheduled commands are ready")
```

### 6.8.C — Permissão do log

```bash
sudo touch /var/www/gente/storage/logs/scheduler.log
sudo chown www-data:www-data /var/www/gente/storage/logs/scheduler.log
sudo chmod 644 /var/www/gente/storage/logs/scheduler.log
```

---

## T6.9 — Configurar Supervisor para queue worker (~15 min)

**Objetivo:** o `ProcessarFolhaJob` (motor de folha refatorado na Fase 3) é despachado assíncrono via `Bus::batch`. Sem queue worker rodando, **a folha NUNCA processa**. Telas mostram "Processando..." eternamente.

### 6.9.A — Instalar Supervisor (se ainda não está)

```bash
sudo apt-get install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### 6.9.B — Criar config do worker

```bash
sudo nano /etc/supervisor/conf.d/gente-worker.conf
```

**Conteúdo:**

```ini
[program:gente-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/gente/artisan queue:work database --queue=default --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/gente/storage/logs/worker.log
stopwaitsecs=3600
```

**Explicação dos flags:**
- `--queue=default`: lê fila default (matches QUEUE_CONNECTION=database default queue)
- `--sleep=3`: 3s entre polls quando não há jobs
- `--tries=3`: até 3 tentativas por job antes de mover para `failed_jobs`
- `--max-time=3600`: cada worker reinicia a cada hora (evita memory leak)
- `--max-jobs=1000`: ou após 1000 jobs (idem)
- `numprocs=2`: 2 workers paralelos (suficiente para 22k servidores; aumentar se backlog crescer)
- `stopwaitsecs=3600`: ao reiniciar, espera até 1h pelo job atual terminar (folha gigante)

### 6.9.C — Recarregar Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start gente-worker:*
```

### 6.9.D — Validar workers rodando

```bash
sudo supervisorctl status gente-worker:*
```

**Esperado:**
```
gente-worker:gente-worker_00   RUNNING   pid 12345, uptime 0:00:05
gente-worker:gente-worker_01   RUNNING   pid 12346, uptime 0:00:05
```

```bash
# Ver tail dos logs
tail -f /var/www/gente/storage/logs/worker.log
# Ctrl+C após 30s — esperado: linhas tipo "Processed: ..." ou silêncio (fila vazia)
```

### 6.9.E — Test job dispatch

```bash
cd /var/www/gente
php artisan tinker
# No prompt:
> dispatch(function () { \Log::info('test-job-from-tinker'); });
> exit

# Aguardar 5s e ver
tail /var/www/gente/storage/logs/worker.log
# Esperado: linha "Processed: ..."

# Ver se logou
grep test-job-from-tinker /var/www/gente/storage/logs/laravel.log
# Esperado: 1+ ocorrência
```

---

## T6.10 — Cache de produção + OPcache (~5 min)

**Objetivo:** ganhar 30-50% de performance via caching de config/routes/views. **APENAS rodar EM PRODUÇÃO** (em dev pode mascarar bugs).

### 6.10.A — Limpar caches velhos primeiro

```bash
cd /var/www/gente
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 6.10.B — Gerar caches otimizados

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**ATENÇÃO `route:cache`:** Laravel não permite cache de rotas que usam `Closure` (rotas inline com `function () {...}`). Se houver erro tipo `Unable to prepare route ... for serialization`, **PARAR** e reportar. Provavelmente alguma rota inline não foi convertida para Controller.

**Saída esperada de cada um:**
```
Configuration cached successfully!
Routes cached successfully!
Views cached successfully!
```

### 6.10.C — Configurar OPcache

```bash
sudo nano /etc/php/8.4/fpm/conf.d/10-opcache.ini
```

**Conteúdo (otimizado para produção):**

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.fast_shutdown=1
opcache.save_comments=1
```

**Explicação:**
- `validate_timestamps=0`: PHP NÃO checa mtime do arquivo a cada request (ganho de perf grande). MAS: significa que **`composer dump-autoload` ou edição de arquivo não tem efeito até PHP-FPM reiniciar**.
- `memory_consumption=256`: 256MB para opcache (KVM 8 tem 32GB, sobra)

### 6.10.D — Reiniciar PHP-FPM

```bash
sudo systemctl restart php8.4-fpm
```

### 6.10.E — Validar OPcache ativo

```bash
php-fpm -i 2>/dev/null | grep -i opcache.enable
# Esperado:
# opcache.enable => On => On
# opcache.enable_cli => Off => Off
```

### 6.10.F — Permissões finais

```bash
sudo chown -R www-data:www-data /var/www/gente
sudo chmod -R 755 /var/www/gente
sudo chmod -R 775 /var/www/gente/storage /var/www/gente/bootstrap/cache
```

---

## T6.11 — Smoke test pós-deploy (~30 min)

**Objetivo:** confirmar que o sistema está funcional ANTES do go-live PMSL. Falhar aqui = ROLLBACK obrigatório.

### 6.11.A — Health endpoint

```bash
curl -i https://gente.saoluis.ma.gov.br/api/v3/health
```

**Esperado:** HTTP 200 (ou 401 se exige auth — verificar). Se 500, **PARAR**.

### 6.11.B — Login admin (Vue 3 SPA)

1. Abrir browser em `https://gente.saoluis.ma.gov.br`
2. Esperar tela de login do Vue 3 SPA carregar (logo PMSL + form)
3. Login com credencial admin (Ronaldo fornece)
4. **Validar:** chega à dashboard sem erro 500

**SE quebrar antes do login:**
- Abrir DevTools → Network: ver qual request 500
- Ver `storage/logs/laravel.log` no servidor (`tail -f storage/logs/laravel.log`)
- Reportar trace

### 6.11.C — CRUD básico de funcionário

Via SPA:
1. Menu → Funcionários
2. Criar 1 funcionário de teste:
   - Nome: "Servidor Teste Deploy"
   - CPF: `00000000000` (CPF teste)
   - Matrícula: `TEST-001`
   - Data início: `2026-05-08`
3. Salvar
4. Validar que aparece na lista
5. Editar (mudar observação) e salvar
6. Confirmar persistência

### 6.11.D — Criar folha teste

Via SPA:
1. Menu → Folhas
2. Criar folha:
   - Descrição: "TESTE PRE-POC 2026-05"
   - Competência: `05/2026`
   - Tipo: 1 (Mensal)
   - Vínculo: 1 (Efetivo)
3. Despachar processamento

**Validações:**

```bash
# Ver fila
tail -f /var/www/gente/storage/logs/worker.log
# Esperado em ~30-60s: "Processed: App\Jobs\ProcessarFolhaJob" + log do MotorFolhaService
```

```sql
USE gente;
GO
-- Confirmar folha criada
SELECT TOP 5 * FROM FOLHA ORDER BY FOLHA_ID DESC;

-- Confirmar DETALHE_FOLHA gerado para o funcionário teste
SELECT * FROM DETALHE_FOLHA WHERE FOLHA_ID = (SELECT MAX(FOLHA_ID) FROM FOLHA);

-- Confirmar EVENTO_DETALHE_FOLHA persistido (Fase 2-B GAP-MF-07)
SELECT TOP 20 * FROM EVENTO_DETALHE_FOLHA ORDER BY EVENTO_DETALHE_FOLHA_ID DESC;
GO
```

### 6.11.E — Holerite/contracheque PDF

```
GET /holerite/pdf/{detalheFolhaId}
```

**Esperado:** PDF baixa corretamente com:
- Cabeçalho PMSL
- Nome do servidor
- Rubricas C1+C2+C3 separadas
- Total proventos / descontos / líquido

### 6.11.F — XML eSocial S-1200 + endpoints migrados na Fase 4 T4.8

Via tinker:

```bash
cd /var/www/gente
php artisan tinker
> $svc = new \App\Services\EsocialXmlService();
> echo $svc->gerarS1200(__FUNCIONARIO_ID_TESTE__, '2026-05', 1, '301');
> exit
```

**Esperado:** XML válido com:
- `<tpAmb>2</tpAmb>` (homologação — config OK)
- `<nrInsc>06205244000149</nrInsc>` (CNPJ PMSL — config OK)
- `<perApur>2026-05</perApur>` (formato correto AAAA-MM)
- `<vrTotCont>...</vrTotCont>` com valor real do funcionário

**Se trouxer `<tpAmb>1</tpAmb>` em produção** = config quebrou. **PARAR.**

**Validação dos endpoints migrados na Fase 4 T4.8** (commits `dee2323` + `a95c5bb`):

```bash
# Confirmar que ferias/alerta-vencer responde sob /api/v3/
curl -i -H "Authorization: Bearer __TOKEN_ADMIN__" \
  https://gente.saoluis.ma.gov.br/api/v3/ferias/alerta-vencer
# Esperado: HTTP 200 com JSON (vazio é OK se não há ferias próximas vencer)

# Confirmar que afastamentos/alerta-expirar responde sob /api/v3/
curl -i -H "Authorization: Bearer __TOKEN_ADMIN__" \
  https://gente.saoluis.ma.gov.br/api/v3/afastamentos/alerta-expirar
# Esperado: HTTP 200 com JSON

# Confirmar que rotas legadas SEM /api/v3/ NÃO existem mais
php artisan route:list 2>/dev/null | grep -iE 'alerta-vencer|alerta-expirar'
# Esperado: APENAS 2 linhas com prefixo /api/v3/
# Se aparecer rota sem prefixo, web.php tem resíduo não removido pelo commit a95c5bb — PARAR
```

### 6.11.G — Cron Schedule rodando

```bash
# Aguardar 5 minutos pós-deploy
sleep 300

# Ver se esocial-processar-fila rodou (deveria ter rodado 1x)
grep esocial-processar-fila /var/www/gente/storage/logs/scheduler.log
# Esperado: 1+ entrada
```

### 6.11.H — Worker queue persistente

```bash
sudo supervisorctl status gente-worker:*
# Esperado: ainda RUNNING (mesmo PID que T6.9.D ou novo se reiniciou pelos limites)
```

### 6.11.I — Logs limpos

```bash
tail -100 /var/www/gente/storage/logs/laravel.log | grep -iE 'error|critical|emergency'
# Esperado: 0 ou pouquíssimas linhas (só warnings tolerados)
```

**Se houver erros que indicam falha funcional, ROLLBACK.**

---

## T6.12 — Plano de rollback documentado (~5 min)

**Documentar antes de precisar.** Ronaldo deve ter este plano impresso/salvo offline.

### 6.12.A — Rollback rápido (cenário "deu ruim em 30 minutos")

```bash
# 1. Parar workers
sudo supervisorctl stop gente-worker:*

# 2. Parar PHP-FPM (interrompe novos requests)
sudo systemctl stop php8.4-fpm

# 3. Restaurar banco do backup T6.1
# No SQL Server Management Studio ou sqlcmd:
USE master;
GO
ALTER DATABASE gente SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
GO
RESTORE DATABASE gente FROM DISK = 'C:\Backup\gente_pre_deploy_2026-05-10.bak'
    WITH REPLACE;
GO
ALTER DATABASE gente SET MULTI_USER;
GO

# 4. Reverter código para tag pré-Fase 6
cd /var/www/gente
git fetch --all --tags
git checkout pre-fase6  # tag criada antes do deploy (ver T6.13.B)

# 5. Reinstalar deps + cache
composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Religar serviços
sudo systemctl start php8.4-fpm
sudo supervisorctl start gente-worker:*
```

### 6.12.B — Criar tag git pré-deploy ANTES da T6.6

**Antygravity DEVE rodar isso ANTES da T6.6 (migrate):**

```bash
cd /var/www/gente
git tag -a pre-fase6 -m "Estado anterior à Fase 6 deploy PMSL — rollback target"
git push origin pre-fase6
```

### 6.12.C — Rollback de migrations (cenário extremo)

Se schema corromper e backup não estiver disponível:

```bash
# Cuidado: derruba TUDO que migrations 2026 criaram
php artisan migrate:rollback --step=N --force
```

**N depende de quantas migrations rodaram.** Confirmar `migrate:status` antes.

### 6.12.D — Comunicação durante rollback

Mensagem padrão para usuários (placar/banner):

> "GENTE temporariamente indisponível para manutenção. Previsão: ~30 min. Equipe RR TECNOL atuando. Suporte: [email/whatsapp]"

---

## T6.13 — Monitor da primeira hora pós-deploy (~60 min)

**Janela crítica.** Manter olhos abertos por 1h após deploy.

### 6.13.A — Janela de logs em 4 abas SSH

**Aba 1:** Laravel
```bash
tail -f /var/www/gente/storage/logs/laravel.log
```

**Aba 2:** Worker
```bash
tail -f /var/www/gente/storage/logs/worker.log
```

**Aba 3:** Scheduler
```bash
tail -f /var/www/gente/storage/logs/scheduler.log
```

**Aba 4:** PHP-FPM + Nginx
```bash
sudo journalctl -u php8.4-fpm -u nginx -f
```

### 6.13.B — Métricas a observar

| Métrica | Comando | Threshold |
|---------|---------|-----------|
| Memória PHP-FPM | `ps aux \| grep php-fpm \| awk '{sum+=$6} END {print sum/1024 " MB"}'` | < 4GB total |
| Workers status | `sudo supervisorctl status gente-worker:*` | sempre RUNNING |
| Jobs pendentes | `php artisan queue:size` | < 100 |
| Failed jobs | `php artisan queue:failed \| wc -l` | 0 |
| DB connections | `SELECT COUNT(*) FROM sys.dm_exec_connections` | < 50 |

### 6.13.C — Alertas que disparam ROLLBACK

- ❌ Failed jobs > 10 em 1h
- ❌ Erros 500 > 5/min sustentado por 5 min
- ❌ Workers reiniciando em loop (`autorestart` infinito)
- ❌ DB CPU > 80% sustentado por 10 min
- ❌ Disk I/O > 90% sustentado

### 6.13.D — Pós-1h: relatório consolidado

```bash
# Total de logs por nível
grep -c 'ERROR' /var/www/gente/storage/logs/laravel.log
grep -c 'CRITICAL' /var/www/gente/storage/logs/laravel.log
grep -c 'WARNING' /var/www/gente/storage/logs/laravel.log
```

```sql
-- Folhas processadas
SELECT COUNT(*) FROM FOLHA WHERE created_at >= DATEADD(hour,-1,GETDATE());

-- DETALHE_FOLHA gerados
SELECT COUNT(*) FROM DETALHE_FOLHA WHERE created_at >= DATEADD(hour,-1,GETDATE());

-- Logins
SELECT COUNT(*) FROM LOGIN_ATTEMPTS WHERE TENTATIVA_EM >= DATEADD(hour,-1,GETDATE()) AND SUCESSO=1;
```

**Se tudo limpo:** sistema validado para go-live PMSL.

---

## T6.14 — Report final (preencher e devolver a Ronaldo/Claude)

```
═══════════════════════════════════════════════════════════════════
FASE 6 — REPORT EXECUÇÃO ANTYGRAVITY (data/hora deploy: ____)
═══════════════════════════════════════════════════════════════════

PRÉ-CONDIÇÕES:
[ ] Fase 1, 2-A, 2-B, fix GAP-MF-04, Fase 3, Fase 5, Fase 4 — todas auditadas
[ ] Branch limpa
[ ] Tag pre-fase6 criada e pushed
[ ] Servidor produção PMSL acessível (SSH OK)

T6.1 — BACKUP:
[ ] 6.1.A — Estado do banco verificado: ___ tabelas, ___ rows totais
[ ] 6.1.B — Backup .bak criado (se aplicável): ___ MB
[ ] Backup copiado para local externo: ___ (localização)

T6.2 — VALIDAÇÃO RUNTIME:
[ ] PHP version: ___ (esperado 8.4.x)
[ ] Extensões PHP missing: ___ (esperado: nenhuma)
[ ] ODBC Driver 18: ___ (esperado: instalado)
[ ] Composer/Node/npm: ___

T6.3 — .ENV PRODUÇÃO:
[ ] .env criado em /var/www/gente/.env
[ ] APP_KEY gerada
[ ] GENTE_PII_BLIND_SALT gerada (hex 64 chars)
[ ] DB credentials preenchidas (não revelar valores no report)
[ ] chmod 600 aplicado
[ ] config:show app.env => ___ (esperado: production)
[ ] config:show database.default => ___ (esperado: sqlsrv)

T6.4 — COMPOSER INSTALL:
[ ] composer install --no-dev --optimize-autoloader executado
[ ] vendor/composer/autoload_classmap.php criado: ___ KB

T6.5 — BUILD ASSETS:
[ ] 6.5.A — npm run prod (Mix legacy): public/css/app.css ___ KB
[ ] 6.5.B — npm run build (Vue 3 SPA): copiado para public/v3/ — ___ arquivos

T6.6 — MIGRATE:
[ ] 6.6.A — migrate:status saída inicial: ___ rodaram, ___ pendentes
[ ] 6.6.B — Smoke conexão DB OK: SQL Server versão ___
[ ] 6.6.C — Backup pré-migrate criado
[ ] 6.6.D — migrate --force executado SEM erros: ___ migrations rodadas
[ ] 6.6.E — pendentes pós-migrate: ___ (esperado 0)
[ ] Total tabelas no banco: ___ (esperado 200+)

T6.7 — SEEDERS:
[ ] RubricasCatalogoSeeder ✓
[ ] EventosBaseSeeder ✓
[ ] RubricasHePlantaoSeeder ✓
[ ] PccvDominioSeeder ✓
[ ] SecretariasSeed ✓
[ ] PerfilSeeder ✓
[ ] RbacMatrixSeeder ✓
[ ] TipoDocumentoSeeder ✓
[ ] Feriados2026Seeder ✓
[ ] TabelaGenericaSeeder ✓
[ ] ConfiguracaoSistemaSeeder ✓
[ ] HoneytokenSeeder ✓
Validações:
[ ] RUBRICA total: ___ (esperado 60+)
[ ] EVENTO total: ___ (esperado 7+)
[ ] Rubricas 030/031/032: ___ (esperado 3)
[ ] CONFIGURACAO_SISTEMA: ___ (esperado 5+)

T6.8 — CRON:
[ ] crontab linha adicionada
[ ] systemctl status cron: active (running)
[ ] scheduler.log existe e cresce a cada minuto

T6.9 — SUPERVISOR/QUEUE:
[ ] /etc/supervisor/conf.d/gente-worker.conf criado
[ ] supervisorctl status: gente-worker_00 + _01 RUNNING
[ ] Test job dispatch funcionou (test-job-from-tinker apareceu no log)

T6.10 — CACHE:
[ ] config:cache executou OK
[ ] route:cache executou OK (NÃO houve erro de Closure)
[ ] view:cache executou OK
[ ] OPcache validado ativo
[ ] PHP-FPM reiniciado

T6.11 — SMOKE TEST:
[ ] /api/v3/health: HTTP ___ (esperado 200/401)
[ ] Login Vue 3 SPA: OK / FALHOU
[ ] CRUD funcionário: OK / FALHOU
[ ] Folha teste criada e processada: OK / FALHOU
[ ] DETALHE_FOLHA gerado: ___ rows
[ ] EVENTO_DETALHE_FOLHA gerado: ___ rows
[ ] Holerite PDF baixado: OK / FALHOU
[ ] eSocial S-1200 XML válido (tpAmb=2, CNPJ correto): OK / FALHOU
[ ] esocial-processar-fila rodou no scheduler: ___ vezes
[ ] Workers ainda RUNNING: SIM / NÃO

T6.12 — ROLLBACK PLAN:
[ ] Tag pre-fase6 criada antes da T6.6
[ ] Plano de rollback documentado e salvo offline pelo Ronaldo

T6.13 — MONITOR 1ª HORA:
[ ] Folhas processadas: ___
[ ] DETALHE_FOLHA criados: ___
[ ] Logins sucesso: ___
[ ] Logins falha: ___
[ ] ERROR no laravel.log: ___ ocorrências
[ ] CRITICAL: ___
[ ] Failed jobs: ___ (esperado 0)
[ ] Memória total php-fpm: ___ MB
[ ] DB CPU pico: ___ %

PROBLEMAS / DECISÕES:
___

TEMPO TOTAL: ___h ___min

DEPLOY APROVADO PARA GO-LIVE SEG 12/05? SIM / NÃO
═══════════════════════════════════════════════════════════════════
```

---

## CHECKLIST PÓS-DEPLOY (até go-live seg 12/05)

### Domingo 10/05 — Noite (após deploy)

- [ ] Todos os Schedule tasks rodaram pelo menos 1x
- [ ] Worker queue processou pelo menos 1 job real
- [ ] Sem ERROR/CRITICAL no laravel.log
- [ ] Backup automático configurado para rodar segunda madrugada

### Segunda 11/05 — Madrugada

- [ ] Smoke test final 06:00 antes do go-live
- [ ] Validar `php artisan gente:healthcheck --json` tudo verde
- [ ] Confirmar cron rodando há 12+ horas sem falha
- [ ] Failed jobs queue: 0

### Segunda 11/05 — Tarde (antes do go-live)

- [ ] Ronaldo + equipe RR TECNOL no servidor monitorando
- [ ] Browsers abertos prontos para acesso inicial dos servidores municipais
- [ ] Plano de rollback impresso/offline em mãos
- [ ] Telefone/WhatsApp do contato PMSL/MA disponível

---

## CRONOGRAMA FINAL

```
[✅] Fase 1 — concluída + auditada (08/05 manhã)
[✅] Fase 2-A — concluída + auditada (08/05 tarde)
[✅] Fase 2-B — concluída + auditada (08/05 noite)
[✅] Fix GAP-MF-04 — concluído + auditado
[✅] Fase 3 — concluída + auditada
[✅] Fase 5 — concluída + auditada
[✅] Fase 4 — concluída + auditada (T4.1→T4.8, 9 commits, incluindo migração `/api/v3/`)
[ ] Fase 6 — dom 10/05 noite + seg 11/05 madrugada (deploy PMSL produção)
[ ] Go-live PMSL — seg 12/05 tarde 🎯
```

**Margem confortável: 4 dias úteis entre fim Fase 5 e go-live.**

---

## CONSIDERAÇÕES FINAIS PARA ANTYGRAVITY

1. **NUNCA confiar em automação para Fase 6.** Cada comando precisa de revisão humana antes de executar em prod.
2. **Sempre 2 pessoas presentes durante deploy** (Ronaldo + 1 backup). Buddy system reduz erros.
3. **Janela de deploy:** dom noite (10/05 22:00 - seg 11/05 04:00). Não deploy em horário comercial.
4. **Comunicação:** Ronaldo avisa contato PMSL antes do deploy. Go-live seg tarde só com sistema validado domingo.
5. **Limites de Antygravity neste contexto:** se Antygravity NÃO TEM acesso SSH/sudo no servidor PMSL, **transcrever todos os comandos** em formato copy-paste. Ronaldo executa e cola saída de volta. Antygravity audita pela saída textual.

**FIM DO PROMPT FASE 6.**
