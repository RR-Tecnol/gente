# NOTA P3 — Shadow Deployment e Conciliação Matemática (2026-04-27)

## Entregáveis implementados (scaffolding técnico)

- Configuração dedicada de shadow em `config/shadow.php`:
  - raiz de snapshot;
  - filas separadas (`queue-shadow-etl`, `queue-shadow-calc`, `queue-shadow-diff`);
  - chunk e limiar padrão de reconciliação.
- Migrations canônicas:
  - `SHADOW_RUN` e `SHADOW_CHECKPOINT` para rastreabilidade/idempotência de execução;
  - `DIFF_RECONCILIACAO` para persistir classificação de divergências por servidor.
- Comandos Artisan:
  - `shadow:snapshot-validar {competencia}` para validar artefatos mínimos;
  - `shadow:dispatch {competencia}` para disparar batches de ETL/cálculo/diff por etapa;
  - `shadow:relatorio-run {run_id}` para consolidar aceite da reconciliação e, com `--persistir`, fechar o `SHADOW_RUN`.
- Jobs de pipeline em batch:
  - `ShadowIngestChunkJob` (ETL cego + checkpoint idempotente);
  - `ShadowCalcChunkJob` (integração com `MotorFolhaService` e persistência em `SHADOW_RESULTADO_CALC`);
  - `ShadowDiffChunkJob` (classificação matemática: exato, tolerável, justificável, crítico).
- Tabela adicional:
  - `SHADOW_RESULTADO_CALC` para armazenar o líquido/proventos/descontos calculado por CPF no run.

## Critérios técnicos já cobertos no scaffolding

- Idempotência por `RUN_ID + IDEMPOTENCY_KEY + ETAPA`.
- Separação explícita de filas por natureza da carga.
- Persistência de trilha de auditoria de execução e diffs.
- Uso de limiar de tolerância configurável para classificação automática.
- Fechamento binário por `RUN_ID` persistido em `SHADOW_RUN` (`aprovado_sem_criticos` vs `reprovado_com_criticos`).

## Próximo passo de implementação de P3

- Conectar o estágio `calc` ao motor real (`MotorFolhaService`) com snapshot determinístico.
- Enriquecer o diff por rubrica e consolidar relatório executivo por competência.
- Consolidar diffs por rubrica (além do líquido) como próxima etapa.
- Executar rodada piloto com 1 competência fechada para calibrar thresholds.

