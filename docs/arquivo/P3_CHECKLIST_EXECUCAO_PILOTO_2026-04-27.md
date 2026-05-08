# P3 — Checklist de Execução Piloto (Competência Fechada)

## Pré-condições

- [ ] `php artisan migrate` aplicado com tabelas `SHADOW_RUN`, `SHADOW_CHECKPOINT`, `DIFF_RECONCILIACAO` e **`job_batches`** (necessária para `Bus::batch()` / `shadow:dispatch`). Se faltar: `php artisan queue:batches-table` (ou use a migration já versionada) e rode `migrate`.
- [ ] Snapshot presente em `storage/app/shadow/YYYY-MM` (ou outro diretório via `--snapshot-dir=`) com:
  - [ ] `manifest.json`
  - [ ] `servidores.csv`
  - [ ] `resultado_legado.csv`
  - [ ] `resultado_gente.csv`
- [ ] **Smoke local (opcional):** fixture versionada em `tests/fixtures/shadow_pilot_minimal/2026-04` (1 CPF, legado = GENTE → `APROVADO_EXATO`). Ver runbook S9.
- [ ] Filas de worker ativas para:
  - [ ] `queue-shadow-etl`
  - [ ] `queue-shadow-calc`
  - [ ] `queue-shadow-diff`

## Execução

- [ ] Validar snapshot (modo mínimo legado):
  - `php artisan shadow:snapshot-validar YYYY-MM`
- [ ] Para competência congelada com `manifest.json` canónico (§15 do plano):
  - `php artisan shadow:snapshot-canonico-validar YYYY-MM --snapshot-dir=...`
- [ ] Após o run, exportar artefatos §15.10:
  - `php artisan shadow:export-run <RUN_ID>`
- [ ] Diff por rubrica (opcional): no diretório do snapshot, par `rubricas_legado.csv` + `rubricas_gente.csv` com colunas `cpf;rubrica_codigo;rubrica_tipo;valor` (e `matricula` opcional).
- [ ] Disparar pipeline completo:
  - `php artisan shadow:dispatch YYYY-MM --etapa=todas --chunk=500`
- [ ] Se usar resultado real do motor no diff:
  - `php artisan shadow:dispatch YYYY-MM --etapa=calc --chunk=500`
  - `php artisan shadow:dispatch YYYY-MM --etapa=diff --diff-source=calc_db --run-id=<RUN_ID>`
- [ ] Aguardar batches e registrar `RUN_ID` gerado.
- [ ] Gerar relatório consolidado:
  - `php artisan shadow:relatorio-run <RUN_ID> --json --persistir`

## Auditoria pós-processamento

- [ ] Contar checkpoints por etapa em `SHADOW_CHECKPOINT`.
- [ ] Consolidar diffs por classe em `DIFF_RECONCILIACAO`:
  - [ ] `APROVADO_EXATO`
  - [ ] `DIVERGENCIA_TOLERAVEL`
  - [ ] `DIVERGENCIA_JUSTIFICAVEL`
  - [ ] `FALHA_SISTEMICA_CRITICA`
- [ ] Abrir ata técnica para qualquer `FALHA_SISTEMICA_CRITICA`.

## Critério binário de aceite P3 (piloto)

- [ ] Zero `FALHA_SISTEMICA_CRITICA`.
- [ ] Divergências justificáveis formalizadas e assinadas.
- [ ] Snapshot versionado e reaplicável sem alteração de saída.
- [ ] `SHADOW_RUN.STATUS` atualizado com status de aceite do run.

