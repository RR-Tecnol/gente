# S4 — Carreira/Progressão (fase 1 entregue, 2026-04-27)

## S4.1 Autorização nominal

- Migration: `database/migrations/2026_04_27_121000_create_progressao_autorizacao_table.php`.
- Nova trilha de autorização para progressão/promoção com:
  - `FUNCIONARIO_ID`, `TIPO_OPERACAO`, `ATO_ADMINISTRATIVO`, `AUTORIZADO_POR`, `EXPIRA_EM`, `UTILIZADA_EM`.
- Endpoint novo: `POST /api/v3/progressao-funcional/autorizar/{id}`.

## Aplicação com trilha

- `POST /api/v3/progressao-funcional/aplicar/{id}` e `POST /api/v3/progressao-funcional/promover/{id}`:
  - aceitam `autorizacao_id` explícita;
  - fallback retrocompatível: se vier apenas `ato`, cria autorização implícita (quando a tabela existe);
  - marca a autorização como utilizada após sucesso.

## Resultado para o fluxo S4

- Passo **autorizar -> aplicar** já suportado em backend.
- Histórico funcional continua gravando ato administrativo e usuário aplicador.

## Pendências S4

- Exigir autorização explícita por perfil/política (desligar fallback implícito quando operação estiver estabilizada).
- Integrar anuênio de forma nativa ao motor de folha e validar impacto com cenários reais.
