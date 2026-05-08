# PROMPT ANTYGRAVITY — FASE 6A: PROVISIONAMENTO INFRAESTRUTURA KVM 8 (PMSL Produção)

> **Pré-requisito:** Hostinger KVM 8 contratado, Ubuntu 22.04.5 LTS instalado, IP `2.24.87.95`, acesso root via senha funcionando.
> **Estimativa total:** 2h-2h30 em modo copy-paste com Ronaldo executando via SSH.
> **CRÍTICO:** este prompt instala SQL Server + PHP + Nginx do zero em servidor de produção real. Ronaldo executa, Antygravity orienta e audita as saídas.

---

## CONTEXTO DA FASE 6A

A Fase 6A é o **provisionamento bare-metal** do servidor de produção do GENTE v3 / SISGEP. Diferente da Fase 6 (deploy do código Laravel), aqui montamos a fundação: SO endurecido, banco de dados SQL Server 2022 Standard, runtime PHP 8.4, Nginx, certificado SSL, domínio funcional. Ao fim desta fase o servidor está **pronto para receber a Fase 6** (deploy do código).

### Stack alvo confirmado

- **Hardware:** KVM 8 — 8 vCPUs, 31 GiB RAM, 388 GB disco
- **SO:** Ubuntu 22.04.5 LTS (kernel 5.15.0-174-generic)
- **DB:** SQL Server 2022 Standard (8 cores licenciados — confirmar com Ronaldo)
- **Domínio:** `sistemagente.com` (DNS já configurado: A record → 2.24.87.95)
- **Runtime:** PHP 8.4-fpm + Nginx + Composer + Node.js 20 LTS
- **Driver DB:** msodbcsql18 + sqlsrv + pdo_sqlsrv (PECL)

### Pré-condições de ambiente já validadas

- Pacotes web: TODOS ausentes (servidor virgem) ✅
- Conectividade: packages.microsoft.com OK, archive.ubuntu.com OK ✅
- Docker: instalado mas sem containers/imagens (vamos desabilitar) ✅
- Hostname atual: `srv1654925` (vamos renomear para `gente-prod`)
- Timezone: UTC (vamos mudar para America/Fortaleza)
- Sem swap configurado (vamos criar 8GB)

### Decisões já tomadas com Ronaldo

1. **Licença SQL Server:** Standard 2022 em compra. Vamos instalar como **Developer Edition** (legal por 180 dias para evaluation/migration), e quando a license key chegar fazer upgrade para Standard SEM perder dados.
2. **Backup:** estratégia inicial backup local diário via cron + dump manual antes do go-live (Bloco 5).
3. **Acesso:** chave SSH ed25519 do Ronaldo já gerada — vamos adicionar ao servidor e desabilitar login por senha.
4. **Docker:** desabilitar (libera RAM, reduz superfície de ataque).

---

## REGRAS CRÍTICAS DE EXECUÇÃO

1. **Modo copy-paste obrigatório.** Antygravity NÃO TEM acesso SSH ao KVM 8. Ronaldo executa cada bloco no terminal e cola a saída completa de volta para auditoria.
2. **Trabalhar em ORDEM ESTRITA:** Bloco 1 → 2 → 3 → 4 → 5. NÃO PULAR.
3. **PARAR e reportar** se QUALQUER comando retornar erro inesperado.
4. **Senhas geradas neste prompt JAMAIS são commitadas.** Ronaldo guarda em gerenciador de senhas (1Password/Bitwarden).
5. **Após cada bloco, VALIDAR antes de prosseguir.** Cada bloco tem seu checkpoint.
6. **Backup do estado anterior:** se algum passo falhar e for preciso reverter, anotar o estado anterior antes de modificar.
7. **NUNCA executar `rm -rf /` ou `dd` em disco do sistema.** Comandos destrutivos têm que ser revisados duas vezes antes de executar.

---

## BLOCO 1 — HARDENING BÁSICO E PREPARAÇÃO DO SISTEMA (~20 min)

### 1.1 — Atualizar pacotes do sistema

```bash
apt update && apt upgrade -y
apt autoremove -y
```

**Esperado:** lista de pacotes atualizados, sem erros. Pode levar 2-5 min.

### 1.2 — Mudar hostname para `gente-prod`

```bash
hostnamectl set-hostname gente-prod
echo "127.0.1.1 gente-prod" >> /etc/hosts

# Validar
hostname
cat /etc/hosts | grep gente-prod
```

**Esperado:**
```
gente-prod
127.0.1.1 gente-prod
```

### 1.3 — Configurar timezone para America/Fortaleza (São Luís/MA)

```bash
timedatectl set-timezone America/Fortaleza
timedatectl
```

**Esperado:** `Time zone: America/Fortaleza (-03, -0300)` e `Local time: ...`

**Por que Fortaleza e não Maranhão?** São Luís usa o mesmo fuso de Fortaleza (-03 sem horário de verão). Não existe `America/Sao_Luis` no banco de dados zoneinfo do Linux. `America/Fortaleza` é o equivalente correto.

### 1.4 — Criar 8GB de swap

```bash
# Criar arquivo swap
fallocate -l 8G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile

# Tornar permanente
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Configurar swappiness (preferir RAM, usar swap só sob pressão real)
echo 'vm.swappiness=10' >> /etc/sysctl.conf
sysctl -p

# Validar
free -h
```

**Esperado:** linha `Swap:` mostrando `8.0Gi total`.

### 1.5 — Adicionar chave SSH do Ronaldo

```bash
mkdir -p /root/.ssh
chmod 700 /root/.ssh

# Adicionar a chave pública (já validada por Claude)
cat >> /root/.ssh/authorized_keys << 'EOF'
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJjd7iWVLwfE1lNqxqRFQq6n51RnwMwc+gVMeHDPxlgz ronaldo-gente-prod
EOF

chmod 600 /root/.ssh/authorized_keys

# Validar
cat /root/.ssh/authorized_keys
```

**ATENÇÃO:** ANTES de seguir para 1.6, **abra OUTRO terminal na sua máquina** e teste:

```powershell
ssh -i C:\Users\joaob\.ssh\id_ed25519 root@2.24.87.95 "echo 'SSH com chave OK' && exit"
```

**Se NÃO funcionar:** PARAR. Não prosseguir para 1.6 até resolver. Sem isso você se tranca fora do servidor.

### 1.6 — Endurecer SSH (DEPOIS de validar 1.5!)

```bash
# Backup do config original
cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak

# Desabilitar autenticação por senha (só por chave)
sed -i 's/^#*PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/^#*PermitRootLogin.*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
sed -i 's/^#*ChallengeResponseAuthentication.*/ChallengeResponseAuthentication no/' /etc/ssh/sshd_config
sed -i 's/^#*UsePAM.*/UsePAM yes/' /etc/ssh/sshd_config

# Validar config sintaxe
sshd -t && echo "sshd config OK" || echo "sshd config ERRO - PARAR"

# Reiniciar SSH (sua sessao atual continua, mas novas conexoes seguirao a nova regra)
systemctl restart ssh

# Confirmar com nova conexao em outro terminal antes de fechar este
```

**TESTE OBRIGATÓRIO antes de fechar a sessão atual:**

Em outro terminal local:
```powershell
ssh -i C:\Users\joaob\.ssh\id_ed25519 root@2.24.87.95 "whoami"
# Deve retornar: root
```

```powershell
ssh root@2.24.87.95 "whoami"
# Deve falhar com: Permission denied (publickey)
```

Se ambos funcionarem como esperado, hardening SSH está OK.

### 1.7 — Instalar e configurar UFW firewall

```bash
apt install -y ufw

# Politica padrao
ufw default deny incoming
ufw default allow outgoing

# Permitir SSH (PORTA 22)
ufw allow 22/tcp comment 'SSH'

# Permitir HTTP/HTTPS (Nginx)
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'

# SQL Server 1433 — NAO abrir externamente (so localhost)
# (Nada a fazer aqui — UFW bloqueia tudo que nao foi explicitamente permitido)

# Ativar firewall
ufw --force enable

# Validar
ufw status verbose
```

**Esperado:**
```
Status: active
Logging: on (low)
Default: deny (incoming), allow (outgoing)

To                         Action      From
--                         ------      ----
22/tcp (SSH)               ALLOW IN    Anywhere
80/tcp (HTTP)              ALLOW IN    Anywhere
443/tcp (HTTPS)            ALLOW IN    Anywhere
```

### 1.8 — Instalar fail2ban (proteção brute force)

```bash
apt install -y fail2ban

# Configuracao customizada para SSH
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = 22
EOF

systemctl enable --now fail2ban
systemctl status fail2ban --no-pager | head -10
```

**Esperado:** `Active: active (running)`.

### 1.9 — Desabilitar Docker (não vamos usar)

```bash
systemctl stop docker
systemctl disable docker
systemctl stop containerd
systemctl disable containerd

# Validar
systemctl is-enabled docker
systemctl is-enabled containerd
```

**Esperado:** ambos retornam `disabled`.

### 1.10 — Criar usuario `deploy` (Laravel nao roda como root)

```bash
adduser --gecos "" --disabled-password deploy
usermod -aG www-data deploy

# Permitir sudo sem senha apenas para servicos especificos (sera usado depois)
cat > /etc/sudoers.d/deploy << 'EOF'
deploy ALL=(root) NOPASSWD: /bin/systemctl reload nginx, /bin/systemctl restart php8.4-fpm, /usr/bin/supervisorctl
EOF
chmod 440 /etc/sudoers.d/deploy

# Adicionar a mesma chave SSH ao usuario deploy
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Validar
id deploy
ls -la /home/deploy/.ssh/
```

**Esperado:** usuário `deploy` criado, grupo `www-data` incluído, chave SSH copiada.

### 1.11 — CHECKPOINT BLOCO 1

Rodar este script de validação completa:

```bash
{
echo "===== VALIDACAO BLOCO 1 ====="
echo "Hostname: $(hostname)"
echo "Timezone: $(timedatectl | grep 'Time zone' | awk '{print $3}')"
echo "Swap: $(free -h | awk '/^Swap:/ {print $2}')"
echo "UFW: $(ufw status | head -1)"
echo "fail2ban: $(systemctl is-active fail2ban)"
echo "Docker: $(systemctl is-enabled docker 2>/dev/null)"
echo "SSH password auth: $(grep '^PasswordAuthentication' /etc/ssh/sshd_config)"
echo "Usuario deploy: $(id deploy 2>&1)"
echo ""
echo "Tudo OK? Se sim, prosseguir para BLOCO 2."
} | tee /tmp/checkpoint-bloco1.txt
```

**Esperado:**
```
Hostname: gente-prod
Timezone: America/Fortaleza
Swap: 8.0Gi
UFW: Status: active
fail2ban: active
Docker: disabled
SSH password auth: PasswordAuthentication no
Usuario deploy: uid=1001(deploy) ...
```

Se TODOS os itens conferem, **prosseguir para BLOCO 2**. Caso contrário, REPORTAR para Claude antes de continuar.

---

## BLOCO 2 — INSTALAR SQL SERVER 2022 (~45 min)

> **NOTA SOBRE LICENÇA:** vamos instalar como **Developer Edition** (legal por 180 dias para evaluation/migration). Quando a license key Standard chegar, fazer upgrade SEM reinstalar via `mssql-conf set-edition` (ver Bloco 2.10).

### 2.1 — Adicionar repositório Microsoft

```bash
# Importar GPG key oficial
curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -

# Adicionar repositório SQL Server 2022 para Ubuntu 22.04
curl https://packages.microsoft.com/config/ubuntu/22.04/mssql-server-2022.list | tee /etc/apt/sources.list.d/mssql-server-2022.list

# Adicionar repositório de tools (sqlcmd, bcp)
curl https://packages.microsoft.com/config/ubuntu/22.04/prod.list | tee /etc/apt/sources.list.d/msprod.list

apt update
```

**Esperado:** sem erros de GPG, sem 404 nos repos.

### 2.2 — Instalar mssql-server (vai baixar ~1.5GB)

```bash
apt install -y mssql-server
```

**Esperado:** binário instalado em `/opt/mssql/`. Pode levar 5-10 min dependendo da rede.

### 2.3 — Configurar SQL Server (setup interativo)

```bash
/opt/mssql/bin/mssql-conf setup
```

**Responder o setup:**

1. **Choose an edition of SQL Server:**
   - Escolher opção **2) Developer (free, no production use rights)**
   - Por que: vamos rodar como Developer até a license Standard chegar. Mesmo binário, sem perda de funcionalidade.

2. **Do you accept the license terms? (Yes/No):** `Yes`

3. **Enter the SQL Server system administrator password:**
   - **GERAR senha forte AGORA** (mínimo 8 chars, maiúscula+minúscula+número+especial)
   - Sugestão: usar `openssl rand -base64 24` em outro terminal e adicionar `!@A1` no fim
   - Exemplo: `XyZkL9mNpQ2vBcDfGhJk!@A1`
   - **ANOTAR EM 1Password/Bitwarden COM LABEL "SQL Server SA - gente-prod"**

4. **Confirm the SQL Server system administrator password:** (digitar de novo)

**Esperado ao fim:**
```
Setup has completed successfully. SQL Server is now starting.
```

### 2.4 — Validar SQL Server rodando

```bash
systemctl status mssql-server --no-pager | head -15
```

**Esperado:** `Active: active (running)` na 3ª linha.

```bash
# Confirmar porta 1433 escutando
ss -tlnp | grep 1433
```

**Esperado:** `LISTEN 0 ... 0.0.0.0:1433 ...` (vamos restringir a localhost no 2.6)

### 2.5 — Instalar tools sqlcmd e bcp

```bash
ACCEPT_EULA=Y apt install -y mssql-tools18 unixodbc-dev

# Adicionar tools ao PATH (permanente)
echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' >> /etc/profile.d/mssql-tools.sh
chmod +x /etc/profile.d/mssql-tools.sh
source /etc/profile.d/mssql-tools.sh

# Validar
which sqlcmd
sqlcmd -? | head -3
```

**Esperado:** `/opt/mssql-tools18/bin/sqlcmd` e versão impressa.

### 2.6 — Restringir SQL Server a localhost (segurança crítica)

```bash
# SQL Server por padrão escuta em 0.0.0.0:1433 (qualquer interface)
# Vamos forçar a escutar SÓ em 127.0.0.1 (localhost)
# A aplicação Laravel está na mesma máquina, então não precisa de acesso remoto

/opt/mssql/bin/mssql-conf set network.ipaddress 127.0.0.1
systemctl restart mssql-server
sleep 5

# Validar
ss -tlnp | grep 1433
```

**Esperado AGORA:** `LISTEN 0 ... 127.0.0.1:1433 ...` (só localhost, sem `0.0.0.0`)

**Por que isso importa:** mesmo com UFW bloqueando 1433 externamente, defesa em profundidade é fundamental. Se UFW falhar por alguma razão, SQL Server ainda só aceita conexões da própria máquina.

### 2.7 — Configurar limite de memória para SQL Server (16 GB)

KVM 8 tem 31 GiB. Reservamos 16 GB para SQL Server, deixando ~14 GB para PHP-FPM/Nginx/OS/cache.

Conectar como SA e configurar:

```bash
# Substituir <SA_PASSWORD> pela senha do passo 2.3
sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -Q "EXEC sp_configure 'show advanced options', 1; RECONFIGURE; EXEC sp_configure 'max server memory', 16384; RECONFIGURE; EXEC sp_configure 'show advanced options', 0; RECONFIGURE;"

# Validar
sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -Q "EXEC sp_configure 'max server memory'"
```

**Esperado:** `config_value` e `run_value` ambos `16384`.

**Nota sobre o flag `-C`:** aceita certificado SSL self-signed do SQL Server (gerado automaticamente na instalação). Em produção real ideal seria certificado próprio, mas como o SQL só escuta localhost, self-signed é aceitável.

### 2.8 — Criar database `gente` e usuário da aplicação

```bash
# Gerar senha forte para o usuário da aplicação
APP_DB_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)$'!A1'
echo "ANOTAR EM 1PASSWORD - SQL Server App User 'gente_app': $APP_DB_PASSWORD"

# Criar database e login
sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C << EOF
CREATE DATABASE gente
COLLATE Latin1_General_CI_AI;
GO

CREATE LOGIN gente_app WITH PASSWORD = '$APP_DB_PASSWORD';
GO

USE gente;
GO

CREATE USER gente_app FOR LOGIN gente_app;
GO

ALTER ROLE db_owner ADD MEMBER gente_app;
GO

PRINT 'Database gente criada com sucesso';
PRINT 'Login gente_app criado e atribuido como db_owner em gente';
GO
EOF
```

**Esperado:** mensagens de sucesso, sem erros.

**Sobre o COLLATE escolhido (`Latin1_General_CI_AI`):**
- `CI` = Case Insensitive (busca por "RONALDO" encontra "ronaldo")
- `AI` = Accent Insensitive (busca por "joao" encontra "joão")
- Padrão recomendado para sistemas brasileiros com dados de pessoas. Compatível com o que o GENTE v3 espera.

### 2.9 — Validar conexão como gente_app

```bash
# Substituir <APP_DB_PASSWORD> pelo valor anotado
sqlcmd -S localhost -U gente_app -P '<APP_DB_PASSWORD>' -C -d gente -Q "SELECT @@VERSION; SELECT DB_NAME() AS database_atual;"
```

**Esperado:**
```
Microsoft SQL Server 2022 ... (Developer Edition)
database_atual: gente
```

### 2.10 — [FUTURO] Upgrade Developer → Standard quando license key chegar

> **PULAR este passo agora.** Executar quando a licença Standard chegar (em horas/dias).

```bash
# Quando a license key chegar:
systemctl stop mssql-server
/opt/mssql/bin/mssql-conf set-edition
# Escolher opção: 4) Standard
# Colar a Product Key quando solicitado
systemctl start mssql-server

# Validar edição
sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -Q "SELECT SERVERPROPERTY('Edition') AS edicao, SERVERPROPERTY('ProductLevel') AS nivel"
# Esperado: edicao = "Standard Edition (64-bit)"
```

**Os dados criados em modo Developer são preservados no upgrade.**

### 2.11 — CHECKPOINT BLOCO 2

```bash
{
echo "===== VALIDACAO BLOCO 2 ====="
echo "SQL Server status: $(systemctl is-active mssql-server)"
echo "Porta 1433: $(ss -tlnp | grep 1433 | awk '{print $4}')"
echo "Edicao SQL: $(sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -h -1 -Q 'SET NOCOUNT ON; SELECT SERVERPROPERTY(''Edition'')' 2>/dev/null | head -1)"
echo "Database gente existe: $(sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -h -1 -Q 'SET NOCOUNT ON; SELECT name FROM sys.databases WHERE name = ''gente''' 2>/dev/null | head -1)"
echo "Login gente_app existe: $(sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -h -1 -Q 'SET NOCOUNT ON; SELECT name FROM sys.sql_logins WHERE name = ''gente_app''' 2>/dev/null | head -1)"
echo "max server memory: $(sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -h -1 -Q 'SET NOCOUNT ON; SELECT value_in_use FROM sys.configurations WHERE name = ''max server memory (MB)''' 2>/dev/null | head -1)"
echo ""
echo "Senhas anotadas em 1Password? SA + gente_app — confirmar"
} | tee /tmp/checkpoint-bloco2.txt
```

**Esperado:**
```
SQL Server status: active
Porta 1433: 127.0.0.1:1433
Edicao SQL: Developer Edition (64-bit)
Database gente existe: gente
Login gente_app existe: gente_app
max server memory: 16384
```

Tudo conferindo, **prosseguir para BLOCO 3**.

---

## BLOCO 3 — STACK WEB LARAVEL: PHP 8.4 + NGINX + COMPOSER + NODE.JS (~30 min)

### 3.1 — Adicionar repositório PHP (Ondrej PPA)

PHP 8.4 não está no repo padrão Ubuntu 22.04 — precisamos do PPA do Ondrej Surý (mantenedor oficial dos pacotes Debian/Ubuntu de PHP).

```bash
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
```

**Esperado:** repositório adicionado, `apt update` lista pacotes do PPA.

### 3.2 — Instalar PHP 8.4-fpm + extensões obrigatórias

```bash
apt install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-common \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-gd \
    php8.4-intl \
    php8.4-readline \
    php8.4-opcache \
    php8.4-dev \
    unixodbc-dev

# Validar versão
php8.4 -v
```

**Esperado:** `PHP 8.4.x (cli)` na primeira linha.

### 3.3 — Instalar driver ODBC Microsoft (msodbcsql18)

```bash
ACCEPT_EULA=Y apt install -y msodbcsql18

# Validar
odbcinst -q -d
```

**Esperado:** lista que inclui `[ODBC Driver 18 for SQL Server]`.

### 3.4 — Instalar extensões PHP sqlsrv + pdo_sqlsrv via PECL

```bash
# pecl precisa do php8.4-dev (já instalado)
pecl install sqlsrv pdo_sqlsrv

# Habilitar extensões no FPM
echo 'extension=sqlsrv.so' > /etc/php/8.4/fpm/conf.d/20-sqlsrv.ini
echo 'extension=pdo_sqlsrv.so' > /etc/php/8.4/fpm/conf.d/20-pdo_sqlsrv.ini

# Habilitar extensões no CLI
echo 'extension=sqlsrv.so' > /etc/php/8.4/cli/conf.d/20-sqlsrv.ini
echo 'extension=pdo_sqlsrv.so' > /etc/php/8.4/cli/conf.d/20-pdo_sqlsrv.ini

# Reiniciar FPM
systemctl restart php8.4-fpm

# Validar extensões carregadas
php8.4 -m | grep -i sqlsrv
```

**Esperado:**
```
pdo_sqlsrv
sqlsrv
```

**Nota durante o `pecl install`:** se aparecerem prompts perguntando sobre headers, aceitar todos com Enter (defaults).

### 3.5 — Validar conexão PHP → SQL Server

```bash
cat > /tmp/test-sqlsrv.php << 'EOF'
<?php
$serverName = "localhost,1433";
$connectionInfo = [
    "Database" => "gente",
    "Uid" => "gente_app",
    "PWD" => "<APP_DB_PASSWORD>",  // SUBSTITUIR pelo valor real
    "TrustServerCertificate" => true,
];

$conn = sqlsrv_connect($serverName, $connectionInfo);
if ($conn === false) {
    echo "FALHA na conexao:\n";
    print_r(sqlsrv_errors());
    exit(1);
}

$stmt = sqlsrv_query($conn, "SELECT @@VERSION AS versao, DB_NAME() AS db");
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
echo "Conexao OK!\n";
echo "Versao: " . substr($row['versao'], 0, 60) . "...\n";
echo "Database: " . $row['db'] . "\n";

sqlsrv_close($conn);
EOF

# Editar o arquivo e substituir <APP_DB_PASSWORD> pelo valor anotado
nano /tmp/test-sqlsrv.php
# (substituir e salvar com Ctrl+O Enter Ctrl+X)

# Executar
php8.4 /tmp/test-sqlsrv.php
```

**Esperado:**
```
Conexao OK!
Versao: Microsoft SQL Server 2022 ...
Database: gente
```

**Se falhar AQUI:** parar e reportar. PHP→SQL é o link crítico, sem isso o Laravel não funciona.

### 3.6 — Configurar PHP-FPM para produção

```bash
# Backup config original
cp /etc/php/8.4/fpm/php.ini /etc/php/8.4/fpm/php.ini.bak

# Configurações de produção em php.ini
sed -i 's/^memory_limit = .*/memory_limit = 512M/' /etc/php/8.4/fpm/php.ini
sed -i 's/^max_execution_time = .*/max_execution_time = 300/' /etc/php/8.4/fpm/php.ini
sed -i 's/^max_input_time = .*/max_input_time = 300/' /etc/php/8.4/fpm/php.ini
sed -i 's/^post_max_size = .*/post_max_size = 50M/' /etc/php/8.4/fpm/php.ini
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/8.4/fpm/php.ini
sed -i 's/^;date.timezone =.*/date.timezone = America\/Fortaleza/' /etc/php/8.4/fpm/php.ini
sed -i 's/^display_errors = .*/display_errors = Off/' /etc/php/8.4/fpm/php.ini
sed -i 's/^display_startup_errors = .*/display_startup_errors = Off/' /etc/php/8.4/fpm/php.ini
sed -i 's/^log_errors = .*/log_errors = On/' /etc/php/8.4/fpm/php.ini
sed -i 's/^expose_php = .*/expose_php = Off/' /etc/php/8.4/fpm/php.ini

# Configurações OPcache (produção)
cat > /etc/php/8.4/fpm/conf.d/10-opcache.ini << 'EOF'
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.fast_shutdown=1
opcache.save_comments=1
EOF

# Mesmas configurações para CLI (php.ini)
sed -i 's/^memory_limit = .*/memory_limit = 512M/' /etc/php/8.4/cli/php.ini
sed -i 's/^;date.timezone =.*/date.timezone = America\/Fortaleza/' /etc/php/8.4/cli/php.ini

systemctl restart php8.4-fpm
systemctl status php8.4-fpm --no-pager | head -10
```

**Esperado:** `Active: active (running)`.

### 3.7 — Instalar Nginx

```bash
apt install -y nginx

systemctl enable --now nginx

# Validar
systemctl is-active nginx
curl -I http://127.0.0.1
```

**Esperado:** `active` e `HTTP/1.1 200 OK` (página default do Nginx).

### 3.8 — Configurar Nginx server block para `sistemagente.com`

```bash
cat > /etc/nginx/sites-available/sistemagente.com << 'EOF'
# HTTP — Let's Encrypt validation + redirect to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name sistemagente.com www.sistemagente.com;

    # Permitir Let's Encrypt validar dominio
    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }

    # Redirect tudo o resto para HTTPS (sera ativado no Bloco 4)
    location / {
        return 301 https://sistemagente.com$request_uri;
    }
}

# HTTPS — servidor principal (config completa após SSL no Bloco 4)
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name sistemagente.com www.sistemagente.com;

    # SSL — placeholder, sera preenchido no Bloco 4
    # ssl_certificate /etc/letsencrypt/live/sistemagente.com/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/sistemagente.com/privkey.pem;

    root /var/www/gente/public;
    index index.php index.html;

    # Tamanhos
    client_max_body_size 50M;

    # Logs
    access_log /var/log/nginx/sistemagente.access.log;
    error_log  /var/log/nginx/sistemagente.error.log;

    # Bloquear acesso a arquivos sensiveis
    location ~ /\.(?!well-known) {
        deny all;
    }
    location ~ \.(env|log|sql|bak)$ {
        deny all;
    }

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 300;
    }

    # Cache de assets estaticos
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Headers de seguranca
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
EOF

# Criar diretorio para Let's Encrypt validar
mkdir -p /var/www/letsencrypt
chown www-data:www-data /var/www/letsencrypt

# Habilitar o site
ln -sf /etc/nginx/sites-available/sistemagente.com /etc/nginx/sites-enabled/sistemagente.com

# Remover default
rm -f /etc/nginx/sites-enabled/default

# COMENTAR temporariamente o bloco HTTPS (sem cert ainda)
sed -i '/listen 443/,/}$/{s/^/# /}' /etc/nginx/sites-available/sistemagente.com

# Validar config
nginx -t
```

**Esperado:** `nginx: configuration file /etc/nginx/nginx.conf test is successful`.

```bash
systemctl reload nginx
```

### 3.9 — Instalar Composer 2.x

```bash
EXPECTED_CHECKSUM="$(php8.4 -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
ACTUAL_CHECKSUM="$(php8.4 -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    echo "ERRO: composer installer checksum mismatch — PARAR"
else
    php8.4 /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
    echo "Composer instalado OK"
fi

# Validar
composer --version
```

**Esperado:** `Composer version 2.x.x ...`.

### 3.10 — Instalar Node.js 20 LTS + npm

```bash
# NodeSource repo para Ubuntu
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Validar
node --version
npm --version
```

**Esperado:** `v20.x.x` e `10.x.x`.

### 3.11 — Criar estrutura de diretórios para o Laravel

```bash
mkdir -p /var/www/gente
chown -R deploy:www-data /var/www/gente
chmod 2775 /var/www/gente

# Validar permissoes
ls -ld /var/www/gente
```

**Esperado:** `drwxrwsr-x ... deploy www-data ... /var/www/gente` (o `s` no grupo é importante — setgid garante que arquivos novos herdem o grupo).

### 3.12 — CHECKPOINT BLOCO 3

```bash
{
echo "===== VALIDACAO BLOCO 3 ====="
echo "PHP version: $(php8.4 -v | head -1)"
echo "PHP-FPM: $(systemctl is-active php8.4-fpm)"
echo "Nginx: $(systemctl is-active nginx)"
echo "PHP modules sqlsrv: $(php8.4 -m | grep -c sqlsrv)"  # esperado 2 (sqlsrv + pdo_sqlsrv)
echo "Composer: $(composer --version 2>&1 | head -1)"
echo "Node: $(node --version)"
echo "npm: $(npm --version)"
echo "ODBC Driver 18: $(odbcinst -q -d 2>&1 | grep -c 'ODBC Driver 18')"  # esperado 1
echo "/var/www/gente existe: $(test -d /var/www/gente && echo SIM || echo NAO)"
echo ""
} | tee /tmp/checkpoint-bloco3.txt
```

**Esperado:**
```
PHP version: PHP 8.4.x ...
PHP-FPM: active
Nginx: active
PHP modules sqlsrv: 2
Composer: Composer version 2.x.x
Node: v20.x.x
npm: 10.x.x
ODBC Driver 18: 1
/var/www/gente existe: SIM
```

Tudo OK, **prosseguir para BLOCO 4**.

---

## BLOCO 4 — DOMÍNIO + SSL (~15 min + tempo de propagação DNS)

### 4.1 — Validar propagação DNS

DNS já configurado pelo Ronaldo:
- `A    @    2.24.87.95    TTL 60`
- `CNAME www → sistemagente.com    TTL 300`

```bash
# Validar resolução
dig +short sistemagente.com
dig +short www.sistemagente.com
```

**Esperado:**
```
2.24.87.95
sistemagente.com.
2.24.87.95
```

**Se vier vazio ou IP errado:** DNS ainda não propagou. Esperar 5-30 min e tentar de novo. **NÃO prosseguir sem DNS resolvendo corretamente** — Let's Encrypt vai falhar.

### 4.2 — Validar conectividade externa para o servidor

```bash
# De OUTRO computador (não o servidor):
curl -I http://sistemagente.com
```

**Esperado:** resposta HTTP do Nginx (mesmo que seja 301 redirect, basta ter resposta).

**Se time out:** UFW pode estar bloqueando, ou DNS ainda não propagou. Validar.

### 4.3 — Instalar Certbot

```bash
apt install -y certbot python3-certbot-nginx
```

### 4.4 — Reabrir bloco HTTPS no Nginx (descomentar)

No bloco 3.8 comentamos o `server { listen 443 ... }`. Vamos descomentar:

```bash
# Restaurar config original e re-aplicar (mais limpo que sed reverso)
cat > /etc/nginx/sites-available/sistemagente.com << 'EOF'
# HTTP — Let's Encrypt + redirect
server {
    listen 80;
    listen [::]:80;
    server_name sistemagente.com www.sistemagente.com;

    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }

    location / {
        return 301 https://sistemagente.com$request_uri;
    }
}
EOF

nginx -t && systemctl reload nginx
```

**Esperado:** `configuration file ... test is successful` e reload sem erro.

### 4.5 — Emitir certificado Let's Encrypt

```bash
certbot --nginx \
    -d sistemagente.com -d www.sistemagente.com \
    --non-interactive \
    --agree-tos \
    --email ronaldo@rrtecnol.com.br \
    --redirect
```

**SUBSTITUIR `ronaldo@rrtecnol.com.br`** pelo email real do Ronaldo (Let's Encrypt envia avisos de expiração para esse email).

**Esperado:**
```
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/sistemagente.com/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/sistemagente.com/privkey.pem
This certificate expires on YYYY-MM-DD.
Successfully deployed certificate for sistemagente.com to /etc/nginx/sites-enabled/sistemagente.com
```

**O Certbot reescreve o server block do Nginx automaticamente para incluir SSL.**

### 4.6 — Refinar config Nginx (Certbot é generosa, vamos aplicar config completa final)

```bash
cat > /etc/nginx/sites-available/sistemagente.com << 'EOF'
# HTTP -> HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name sistemagente.com www.sistemagente.com;

    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }

    location / {
        return 301 https://sistemagente.com$request_uri;
    }
}

# HTTPS principal
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name sistemagente.com www.sistemagente.com;

    ssl_certificate     /etc/letsencrypt/live/sistemagente.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sistemagente.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 10m;

    root /var/www/gente/public;
    index index.php index.html;

    client_max_body_size 50M;

    access_log /var/log/nginx/sistemagente.access.log;
    error_log  /var/log/nginx/sistemagente.error.log;

    # Bloquear acesso a arquivos sensiveis
    location ~ /\.(?!well-known) {
        deny all;
    }
    location ~ \.(env|log|sql|bak)$ {
        deny all;
    }

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 300;
    }

    # Cache de assets estaticos
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Headers de seguranca
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
EOF

nginx -t && systemctl reload nginx
```

**Esperado:** `successful` e reload OK.

### 4.7 — Validar HTTPS funcionando

```bash
# De OUTRO computador (sua máquina):
curl -I https://sistemagente.com
```

**Esperado:**
```
HTTP/2 200 (ou 404 — depende, sem app ainda)
strict-transport-security: max-age=31536000; includeSubDomains
x-frame-options: SAMEORIGIN
...
```

**Se retornar 502 Bad Gateway:** PHP-FPM tem problema. Verificar `systemctl status php8.4-fpm`.
**Se retornar 404:** OK, esperado — sem app deployada ainda. O importante é que SSL está OK.

### 4.8 — Validar renovação automática Let's Encrypt

```bash
# Certbot instala um timer systemd automaticamente
systemctl status certbot.timer --no-pager | head -10

# Simular renovação (não renova de fato, só testa)
certbot renew --dry-run
```

**Esperado:** `Congratulations, all simulated renewals succeeded`.

### 4.9 — CHECKPOINT BLOCO 4

```bash
{
echo "===== VALIDACAO BLOCO 4 ====="
echo "DNS sistemagente.com: $(dig +short sistemagente.com)"
echo "DNS www: $(dig +short www.sistemagente.com)"
echo "Cert path: $(ls /etc/letsencrypt/live/sistemagente.com/fullchain.pem 2>&1)"
echo "Cert expira: $(openssl x509 -in /etc/letsencrypt/live/sistemagente.com/fullchain.pem -noout -enddate 2>/dev/null)"
echo "Certbot timer: $(systemctl is-active certbot.timer)"
echo "Nginx: $(systemctl is-active nginx)"
echo ""
echo "Teste HTTPS de fora — rodar de OUTRA maquina:"
echo "curl -I https://sistemagente.com"
} | tee /tmp/checkpoint-bloco4.txt
```

**Esperado:**
```
DNS sistemagente.com: 2.24.87.95
DNS www: <CNAME → sistemagente.com → 2.24.87.95>
Cert path: /etc/letsencrypt/live/sistemagente.com/fullchain.pem
Cert expira: notAfter=<data ~90 dias no futuro>
Certbot timer: active
Nginx: active
```

Tudo OK, **prosseguir para BLOCO 5**.

---

## BLOCO 5 — BACKUP + FINALIZAÇÃO (~15 min)

### 5.1 — Configurar backup diário SQL Server

```bash
# Criar diretorio de backups
mkdir -p /var/backups/mssql
chown -R mssql:mssql /var/backups/mssql
chmod 750 /var/backups/mssql

# Script de backup
cat > /usr/local/bin/backup-gente.sh << 'EOF'
#!/bin/bash
# Backup diario do database gente

BACKUP_DIR="/var/backups/mssql"
DATE=$(date +%Y-%m-%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/gente_$DATE.bak"
SA_PASSWORD="<SA_PASSWORD>"  # SUBSTITUIR pelo valor real e usar chmod 600

# Executar backup
sqlcmd -S localhost -U SA -P "$SA_PASSWORD" -C -Q "BACKUP DATABASE gente TO DISK = '$BACKUP_FILE' WITH FORMAT, COMPRESSION, NAME = 'gente daily backup', STATS = 10"

# Permissoes corretas
chown mssql:mssql "$BACKUP_FILE"
chmod 640 "$BACKUP_FILE"

# Manter apenas ultimos 14 dias
find "$BACKUP_DIR" -name "gente_*.bak" -mtime +14 -delete

# Log
echo "$(date '+%Y-%m-%d %H:%M:%S') Backup OK: $BACKUP_FILE ($(du -h $BACKUP_FILE | cut -f1))" >> /var/log/backup-gente.log
EOF

# IMPORTANTE: substituir <SA_PASSWORD> antes de tornar executável
nano /usr/local/bin/backup-gente.sh
# (substituir e salvar)

chmod 700 /usr/local/bin/backup-gente.sh
chown root:root /usr/local/bin/backup-gente.sh

# Cron diario as 03:00 da manha (horario de Fortaleza)
echo "0 3 * * * root /usr/local/bin/backup-gente.sh" > /etc/cron.d/backup-gente
chmod 644 /etc/cron.d/backup-gente

# Testar agora
/usr/local/bin/backup-gente.sh
ls -la /var/backups/mssql/
cat /var/log/backup-gente.log
```

**Esperado:** arquivo `.bak` criado em `/var/backups/mssql/`, log com sucesso.

### 5.2 — Configurar logrotate para logs Nginx + Laravel

```bash
# Nginx ja tem logrotate padrão, mas vamos validar
cat /etc/logrotate.d/nginx | head -10

# Logrotate customizado para Laravel (sera usado quando deploy acontecer)
cat > /etc/logrotate.d/gente-laravel << 'EOF'
/var/www/gente/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 deploy www-data
    sharedscripts
}
EOF
```

### 5.3 — Configurar Hostinger backup automático no painel

**MANUAL — fazer no painel Hostinger (não via SSH):**

1. Acessar `hpanel.hostinger.com`
2. VPS → KVM 8 → Snapshots e Backups
3. Ativar **"Backup automático"** (custo extra ~R$ X/mês — confirmar valor)
4. Configurar frequência: diário
5. Retenção: 7-14 dias

**Por que duas camadas de backup:**
- **Backup local SQL Server (cron)**: rápido, usa `BACKUP DATABASE` nativo, restora em minutos
- **Backup Hostinger**: snapshot completo do disco — protege contra perda total da VM (incêndio, ataque ransomware, etc.)

### 5.4 — Preparar diretório do Laravel para deploy (Fase 6)

```bash
# Apenas garantir estrutura, deploy real vira na Fase 6
ls -ld /var/www/gente

# Criar arquivo de teste para validar Nginx + PHP funcionando
cat > /var/www/gente/public/info.php << 'EOF'
<?php
phpinfo();
EOF

# Permissoes
chown -R deploy:www-data /var/www/gente
chmod 644 /var/www/gente/public/info.php

# Criar pasta public (que o Nginx aponta)
mkdir -p /var/www/gente/public
mv /var/www/gente/info.php /var/www/gente/public/info.php 2>/dev/null || true

# Restart final
systemctl reload nginx
systemctl reload php8.4-fpm
```

**Testar via browser:** `https://sistemagente.com/info.php`

**Esperado:** página phpinfo() do PHP 8.4 com seções para sqlsrv e pdo_sqlsrv.

**APÓS validar:** REMOVER o info.php (vaza informações sensíveis):

```bash
rm /var/www/gente/public/info.php
```

### 5.5 — Habilitar atualizações automáticas de segurança

```bash
apt install -y unattended-upgrades

cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};

Unattended-Upgrade::Package-Blacklist {
    "mssql-server";
    "msodbcsql18";
    "nginx";
    "php8.4*";
};

Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Automatic-Reboot-Time "04:00";
EOF

cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF

systemctl restart unattended-upgrades

# Validar
systemctl is-active unattended-upgrades
```

**Por que blacklist de mssql/nginx/php:** atualizações desses pacotes podem quebrar o sistema. Vamos atualizá-los manualmente em janelas planejadas.

### 5.6 — CHECKPOINT FINAL — VALIDAÇÃO COMPLETA

```bash
{
echo "════════════════════════════════════════════════════"
echo " CHECKPOINT FINAL — FASE 6A INFRA"
echo " $(date '+%Y-%m-%d %H:%M:%S')"
echo "════════════════════════════════════════════════════"
echo ""
echo "[BLOCO 1] HARDENING"
echo "  Hostname:         $(hostname)"
echo "  Timezone:         $(timedatectl | grep 'Time zone' | awk '{print $3}')"
echo "  Swap:             $(free -h | awk '/^Swap:/ {print $2}')"
echo "  UFW:              $(ufw status | head -1 | awk '{print $2}')"
echo "  fail2ban:         $(systemctl is-active fail2ban)"
echo "  SSH password:     $(grep '^PasswordAuthentication' /etc/ssh/sshd_config | awk '{print $2}')"
echo ""
echo "[BLOCO 2] SQL SERVER"
echo "  Status:           $(systemctl is-active mssql-server)"
echo "  Porta:            $(ss -tlnp | grep 1433 | awk '{print $4}')"
echo "  Edicao:           $(/opt/mssql-tools18/bin/sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -h -1 -Q 'SET NOCOUNT ON; SELECT SERVERPROPERTY(''Edition'')' 2>/dev/null | head -1 | xargs)"
echo "  Database gente:   $(/opt/mssql-tools18/bin/sqlcmd -S localhost -U SA -P '<SA_PASSWORD>' -C -h -1 -Q 'SET NOCOUNT ON; SELECT name FROM sys.databases WHERE name = ''gente''' 2>/dev/null | head -1 | xargs)"
echo ""
echo "[BLOCO 3] STACK WEB"
echo "  PHP:              $(php8.4 -v | head -1 | awk '{print $2}')"
echo "  PHP-FPM:          $(systemctl is-active php8.4-fpm)"
echo "  Nginx:            $(systemctl is-active nginx)"
echo "  sqlsrv ext:       $(php8.4 -m | grep -c sqlsrv)"
echo "  Composer:         $(composer --version 2>&1 | awk '{print $3}')"
echo "  Node:             $(node --version)"
echo ""
echo "[BLOCO 4] DOMINIO + SSL"
echo "  DNS:              $(dig +short sistemagente.com | head -1)"
echo "  Cert:             $(test -f /etc/letsencrypt/live/sistemagente.com/fullchain.pem && echo OK || echo AUSENTE)"
echo "  Cert expira:      $(openssl x509 -in /etc/letsencrypt/live/sistemagente.com/fullchain.pem -noout -enddate 2>/dev/null | cut -d= -f2)"
echo "  Certbot timer:    $(systemctl is-active certbot.timer)"
echo ""
echo "[BLOCO 5] BACKUP + FINALIZACAO"
echo "  Backup script:    $(test -x /usr/local/bin/backup-gente.sh && echo OK || echo AUSENTE)"
echo "  Cron backup:      $(test -f /etc/cron.d/backup-gente && echo OK || echo AUSENTE)"
echo "  Auto-upgrades:    $(systemctl is-active unattended-upgrades)"
echo "  /var/www/gente:   $(test -d /var/www/gente && echo OK || echo AUSENTE)"
echo ""
echo "════════════════════════════════════════════════════"
echo " STATUS: SERVIDOR PRONTO PARA RECEBER FASE 6 (DEPLOY)"
echo "════════════════════════════════════════════════════"
} | tee /tmp/checkpoint-final-fase6a.txt
```

**Saída esperada (todos os campos preenchidos):**
```
[BLOCO 1] HARDENING
  Hostname:         gente-prod
  Timezone:         America/Fortaleza
  Swap:             8.0Gi
  UFW:              active
  fail2ban:         active
  SSH password:     no

[BLOCO 2] SQL SERVER
  Status:           active
  Porta:            127.0.0.1:1433
  Edicao:           Developer Edition (64-bit)
  Database gente:   gente

[BLOCO 3] STACK WEB
  PHP:              8.4.x
  PHP-FPM:          active
  Nginx:            active
  sqlsrv ext:       2
  Composer:         2.x.x
  Node:             v20.x.x

[BLOCO 4] DOMINIO + SSL
  DNS:              2.24.87.95
  Cert:             OK
  Cert expira:      <data ~90 dias>
  Certbot timer:    active

[BLOCO 5] BACKUP + FINALIZACAO
  Backup script:    OK
  Cron backup:      OK
  Auto-upgrades:    active
  /var/www/gente:   OK
```

---

## REPORT FINAL — FASE 6A (preencher e devolver a Ronaldo/Claude)

```
═══════════════════════════════════════════════════════════════════
FASE 6A — REPORT EXECUCAO INFRA (data/hora: ____)
═══════════════════════════════════════════════════════════════════

PRE-CONDICOES:
[ ] KVM 8 acessivel via SSH com chave
[ ] DNS sistemagente.com propagado e resolvendo para 2.24.87.95
[ ] Conectividade externa OK (Microsoft, Ubuntu)

BLOCO 1 — HARDENING:
[ ] Pacotes atualizados
[ ] Hostname: gente-prod
[ ] Timezone: America/Fortaleza
[ ] Swap: 8 GB ativo
[ ] Chave SSH adicionada (root + deploy)
[ ] SSH password disabled
[ ] UFW ativo: 22, 80, 443
[ ] fail2ban ativo
[ ] Docker desabilitado
[ ] Usuario deploy criado

BLOCO 2 — SQL SERVER:
[ ] mssql-server instalado (Developer Edition)
[ ] Senha SA gerada e anotada em 1Password
[ ] mssql-tools18 instalado (sqlcmd OK)
[ ] Restringido a 127.0.0.1
[ ] max server memory = 16384 MB
[ ] Database gente criado
[ ] Login gente_app criado e anotado
[ ] [FUTURO] Upgrade Standard quando key chegar

BLOCO 3 — STACK WEB:
[ ] PHP 8.4-fpm + extensoes
[ ] msodbcsql18 (ODBC Driver 18)
[ ] sqlsrv + pdo_sqlsrv via PECL
[ ] Conexao PHP -> SQL Server validada
[ ] Configs PHP de producao aplicadas (memory_limit, OPcache)
[ ] Nginx instalado
[ ] Server block sistemagente.com criado
[ ] Composer 2.x
[ ] Node.js 20 LTS + npm
[ ] /var/www/gente com permissoes corretas

BLOCO 4 — SSL:
[ ] DNS resolvendo
[ ] Certbot instalado
[ ] Certificado emitido
[ ] HTTPS funcionando
[ ] HSTS + headers seguranca
[ ] Renovacao automatica testada (--dry-run OK)

BLOCO 5 — BACKUP + FINAL:
[ ] Script backup-gente.sh criado e testado
[ ] Cron diario 03:00 ativo
[ ] Hostinger backup automatico ativado no painel
[ ] logrotate Laravel preparado
[ ] unattended-upgrades configurado (com blacklist)
[ ] info.php removido apos teste

PROBLEMAS / DECISOES:
___

TEMPO TOTAL: ___h ___min

PRONTO PARA FASE 6 (DEPLOY)? SIM / NAO

OBSERVACOES:
- License key SQL Server Standard: STATUS ___
- Backup automatico Hostinger: ATIVO / PENDENTE
- Email Let's Encrypt configurado: ___

═══════════════════════════════════════════════════════════════════
```

---

## CRONOGRAMA RECOMENDADO

```
[ ] Sex 09/05 — hoje:
    [ ] Comprar licença SQL Server Standard (em paralelo)
    [ ] Executar Bloco 1 (~20 min) — Hardening
    [ ] Executar Bloco 2 (~45 min) — SQL Server Developer
    [ ] Pausa: validar com Claude

[ ] Sab 10/05 — manha:
    [ ] Executar Bloco 3 (~30 min) — Stack web
    [ ] Executar Bloco 4 (~15 min) — SSL
    [ ] Executar Bloco 5 (~15 min) — Backup
    [ ] Validar checkpoint final
    [ ] Reportar para Claude — autorizar Fase 6

[ ] Sab 10/05 — tarde / Dom 11/05:
    [ ] Fase 6 (deploy do código Laravel)
    [ ] Smoke tests
    [ ] Monitor primeira hora

[ ] Seg 12/05 — tarde:
    [ ] GO-LIVE PMSL real
```

---

## CONSIDERACOES FINAIS

1. **Senhas geradas neste prompt:** SA do SQL Server e gente_app. AMBAS em 1Password ANTES de fechar o terminal.
2. **Chave SSH privada (id_ed25519):** backup em pen drive criptografado. Se perder, Ronaldo perde acesso permanente.
3. **License key Standard:** quando chegar, executar Bloco 2.10 (~5 min). Não precisa reinstalar.
4. **Monitor pós-Fase 6A:** deixar `/tmp/checkpoint-final-fase6a.txt` salvo como prova do estado inicial. Se algo mudar depois, comparar.
5. **Antygravity sem SSH:** todo o prompt foi desenhado para Ronaldo executar manualmente. Antygravity orienta passo a passo e audita as saídas que Ronaldo cola de volta.

**FIM DO PROMPT FASE 6A.**
