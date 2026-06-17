# P0 — Política de incidentes (2026-04-27)

## Classificação

- **P1 (Crítico):** impacto em folha, indisponibilidade relevante, risco financeiro/jurídico imediato.
- **P2 (Alto):** degradação relevante com contorno disponível.
- **P3 (Médio/Baixo):** falhas sem impacto direto no pagamento/obrigação legal.

## Fluxo de resposta

1. Detectar (alerta automático ou reporte manual).
2. Classificar severidade (P1/P2/P3).
3. Conter impacto (feature flag, pausa de fila, rollback, bloqueio controlado).
4. Corrigir causa raiz.
5. Registrar pós-mortem com ações preventivas.

## Comunicação

- P1: notificação imediata para operação + responsáveis institucionais.
- P2: notificação técnica e atualização periódica até estabilização.
- P3: registro e correção em janela planejada.

## Regras operacionais

- Todo incidente P1/P2 exige evidência: horário, impacto, comando/ação aplicada e resultado.
- Se houver dúvida entre estabilidade e continuidade, priorizar contenção segura (no-go temporário).
