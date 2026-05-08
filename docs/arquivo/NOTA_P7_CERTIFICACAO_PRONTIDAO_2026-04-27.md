# P7 — Certificação de Prontidão (2026-04-27)

## Entregável executável

- Comando criado: `php artisan gente:prontidao-certificar`
- Opções:
  - `--json`
  - `--skip-db`
- Saída inclui:
  - `go_live_decisao` (`go`/`no-go`)
  - `blockers[]` com códigos de bloqueio.

## Critérios avaliados (pass/fail)

- Runtime:
  - PHP >= 8.2
  - extensão `bcmath` habilitada
- Base de homologação matemática:
  - tabelas `SHADOW_RUN`, `DIFF_RECONCILIACAO`, `SHADOW_RESULTADO_CALC`
  - zero `FALHA_SISTEMICA_CRITICA` nos últimos 30 dias
- Operação eSocial:
  - colunas de DLQ (`DEAD_LETTER_AT`, `DEAD_LETTER_REASON`) disponíveis.

## Objetivo

- Transformar checklist de prontidão em verificação técnica objetiva e reexecutável para gate de go-live.
- Formalizar bloqueio/desbloqueio com rastreabilidade operacional (`docs/P7_MATRIZ_BLOQUEIO_GOLIVE_2026-04-27.md`).
- Conexão SQL Server: `DB_LOGIN_TIMEOUT` em `config/database.php` (padrão 8s) para evitar travamentos longos quando o banco está indisponível.

