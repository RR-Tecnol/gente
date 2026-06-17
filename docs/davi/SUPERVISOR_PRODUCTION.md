# Supervisor (produção) — filas Laravel `queue:work`

Este documento descreve como manter o processamento assíncrono da folha (e demais jobs) **sempre ativo** no servidor Linux da prefeitura, usando [Supervisor](http://supervisord.org/). Substitua caminhos, utilizador e versão de PHP pelos valores reais do ambiente.

## Pré-requisitos

- PHP CLI com as mesmas extensões e `php.ini` que o PHP-FPM (ou o SAPI em uso).
- Código da aplicação em disco (ex.: `/var/www/gente`).
- Ficheiro `.env` de produção com `QUEUE_CONNECTION=database` ou `redis` (nunca `sync` em produção para batches reais).
- Tabela `jobs` migrada (driver `database`) ou Redis configurado.

## Instalação do Supervisor (RHEL/Fedora exemplo)

```bash
sudo dnf install -y supervisor
sudo systemctl enable --now supervisord
```

Em Debian/Ubuntu o pacote costuma chamar-se `supervisor` e o serviço `supervisor`.

## Programa: grupo de workers Laravel

Crie um ficheiro por aplicação, por exemplo `/etc/supervisord.d/gente-queue.ini` (o include exacto depende da distribuição; em alguns sistemas é `/etc/supervisor/conf.d/gente-queue.conf`).

Conteúdo recomendado — **4 processos** `queue:work` (ajuste `--queue=` se usar filas nomeadas):

```ini
[program:gente-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/gente/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=apache
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/gente-queue-worker.log
stopwaitsecs=3600
```

Notas:

- Troque `/usr/bin/php` pelo caminho do PHP CLI (`which php`).
- Troque `/var/www/gente` pelo deploy real.
- Troque `user=apache` por `www-data`, `nginx`, `gente`, etc., conforme o dono dos ficheiros da app.
- Se usar Redis: `queue:work redis --sleep=3 ...`.
- `--max-time=3600` reinicia o worker de hora a hora para mitigar fugas de memória; pode alinhar com a política interna.
- `numprocs=4` cria quatro processos independentes; aumente se a fila e o CPU o permitirem.

## Aplicar configuração

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status gente-queue-worker:*
```

Deve listar quatro entradas `RUNNING`. Em caso de erro, consulte `stdout_logfile` e o log do Laravel (`storage/logs`).

## Deploy (reinício controlado)

Após cada deploy de código ou alteração de `.env` que afete filas:

```bash
sudo supervisorctl restart gente-queue-worker:*
```

## HTTP (referência)

O `php artisan serve` **não** deve ser usado em produção. Use PHP-FPM + Nginx/Apache. O Supervisor aqui cobre apenas os **workers** de fila.

## Resumo

| Item | Valor típico |
|------|----------------|
| Ferramenta | supervisord |
| Comando | `php artisan queue:work` |
| Instâncias | 4 (`numprocs=4`) |
| Reinício | `autorestart=true` |
| Logs | ficheiro dedicado + `storage/logs` Laravel |
