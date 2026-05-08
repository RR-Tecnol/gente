# P0 — Catálogo SLO/SLA/KRI (2026-04-27)

## SLO (engenharia)

1. Disponibilidade das rotas críticas >= 99,9% no horário comercial.
2. p95 de endpoints operacionais <= 500ms.
3. Scheduler crítico com taxa de sucesso >= 99% por janela semanal.

## SLA (negócio)

1. Fechamento de folha de competência dentro da janela acordada (meta inicial <= 45 min).
2. Incidente crítico P1 com resposta inicial <= 15 min e contenção <= 2h.

## KRI (risco)

1. Fila crítica com falha persistente acima do limiar definido pelo comitê.
2. Dead Letter Queue acima do limiar acordado para jobs críticos.
3. Rotas de mutação sem middleware de perfil (meta absoluta: 0).
4. Falha de cron crítico sem alerta operacional (meta: 0).

## Coleta e evidência

- Fonte primária: logs de aplicação, métricas de fila e registros de execução de scheduler.
- Frequência de revisão: diária na operação assistida; semanal no comitê técnico.
