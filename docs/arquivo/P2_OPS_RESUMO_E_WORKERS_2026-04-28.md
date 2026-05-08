# P2 — Resumo operacional e workers Shadow

## Comando `gente:ops-resumo --json`

Consolida indicadores mínimos:

- **`failed_jobs`:** `count` (tamanho exacto da tabela de falhas), `por_fila` (agregado por coluna `queue`), `total_payload_bytes` (soma do tamanho do payload; SQL Server via `DATALENGTH`, resto `LENGTH`), e `amostra_ultimas` (últimos N id/uuid, default `N=5`, use `--amostra-falhas=0` para desligar).
- **`job_batches`:** pendentes e com `failed_jobs > 0`.
- **Últimos `SHADOW_RUN`**, **eSocial** `FALHA_PERMANENTE` quando existir tabela.

Uso em monitoração ou cron de leitura (sem credenciais extra).

## Workers das filas shadow (§15.8)

Com `QUEUE_CONNECTION=database` (ou `redis` com a mesma convenção de nomes de fila), subir processamento assíncrono:

```bash
cd gente && ./scripts/queue_workers_shadow.sh
```

O script executa `queue:work` com `queue-shadow-etl`, `queue-shadow-calc`, `queue-shadow-diff` e `default` (nessa ordem de prioridade no parâmetro).

Em Docker de desenvolvimento, normalmente o `app` sobe só `php-fpm`; nesse caso o worker corre **no host** ou num serviço dedicado. Não requer `sudo`.
