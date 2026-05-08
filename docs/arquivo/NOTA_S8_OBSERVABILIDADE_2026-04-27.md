# S8 — Observabilidade e confiabilidade (2026-04-27)

## Evolução aplicada

- `gente:healthcheck` ampliado para cobrir prontidão de plataforma:
  - check de versão mínima de PHP (`>=8.2`);
  - check de extensão `bcmath` carregada;
  - check de tabelas P3 (`SHADOW_RUN`, `DIFF_RECONCILIACAO`).
- Métrica adicional operacional:
  - `shadow_diff_critico_30d` para monitorar divergência matemática crítica no período.

# S8 — Transparência ativa/LGPD/observabilidade (fase 1 entregue, 2026-04-27)

## Endpoint de observabilidade pública

- Novo endpoint: `GET /api/v3/transparencia/observabilidade-integracoes`
- Métricas entregues:
  - exportações de transparência nos últimos 7 dias
  - eSocial pendente/rejeitado
  - bloqueios/desbloqueios RPPS (30 dias)

## Objetivo da fase

- Dar visibilidade operacional mínima para controle interno e acompanhamento público sem expor dados pessoais.
- Próxima fase S8: publicar catálogo de dados, janela de atualização e evidências formais para órgãos de controle.
