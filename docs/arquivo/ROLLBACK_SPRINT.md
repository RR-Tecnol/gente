# Rollback por sprint — padrão (S0.3)

Baseado no plano `PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26`, secção 8.

## Quando

- Degradação em produção/homolog atribuível ao deploy recente
- Regressão crítica (auth, rota, perda de dados) não mitigada em janela acordada

## Passos (ordem sugerida)

1. **Parar a sangria** — desativar feature flag ou reverter o merge/deploy que introduziu a alteração.
2. **Reverter artefato** — `git revert` do(s) commit(s) da sprint (preferível a `reset` em branches compartilhados).
3. **Reimplantar** — build + deploy do commit estável conhecido.
4. **Smoke mínimo** — login, rota crítica da sprint anterior, 1 leitura + 1 escrita de teste em homolog.
5. **Comunicar** — registrar ocorrência (quem, quando, o quê) e reabrir item no backlog se a sprint não puder ser reentregue na mesma janela.

## O que **não** fazer

- Apagar branch histórica sem backup
- Aplicar hotfix ad hoc sem backport para `main`/`develop` conforme fluxo do time

## Dados e integrações

- Se a sprint alterou tabelas ou filas, validar com DBA/infra **script de rollback** ou plano de compensação (insert inverso, job de reprocesso) *antes* de reverter aplicação.
