# P7 — Matriz de Bloqueio/Desbloqueio de Go-Live (2026-04-27)

## Regra executável

- O comando `gente:prontidao-certificar` produz:
  - `status` (`pass`/`fail`)
  - `go_live_decisao` (`go`/`no-go`)
  - lista de `blockers` com código e mensagem.

## Blockers padrão

- `BLOQ-P0-DB-CONN`
  - Condição: `db_conectividade = false`
  - Efeito: `no-go` (demais checks de banco são ignorados na lista de blockers para evitar ruído).
- `BLOQ-P1-PHP`
  - Condição: `php_minimo_82 = false`
  - Efeito: `no-go`.
- `BLOQ-P1-BCMATH`
  - Condição: `bcmath_habilitado = false`
  - Efeito: `no-go`.
- `BLOQ-P3-SCHEMA`
  - Condição: `shadow_schema_ok = false` (tabelas `SHADOW_RUN`, `DIFF_RECONCILIACAO`, `SHADOW_RESULTADO_CALC` e **`job_batches`** — necessária para `shadow:dispatch` em batch)
  - Efeito: `no-go`.
- `BLOQ-P3-CRITICO`
  - Condição: `shadow_sem_critico_30d = false`
  - Efeito: `no-go`.
- `BLOQ-P2-DLQ`
  - Condição: `esocial_dlq_visivel = false`
  - Efeito: `no-go`.

## Critério de desbloqueio

- Go-live só é liberado quando `blockers = []` e `go_live_decisao = go`.

