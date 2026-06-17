# S7 — Conformidade federal (fase 1 entregue, 2026-04-27)

## eSocial: fila/retry/idempotência

- Migration: `2026_04_27_122000_add_retry_columns_to_esocial_evento_table.php`
  - adiciona `RETRY_COUNT`, `NEXT_RETRY_AT`, `LAST_ERROR`, `IDEMPOTENCY_KEY`.
- Rota nova: `POST /api/v3/esocial/eventos/{id}/enfileirar`
  - marca evento como `PENDENTE_ENVIO` e define `idempotency_key`.
- Comando: `php artisan esocial:processar-fila --limit=80`
  - processa `PENDENTE_ENVIO`/`REJEITADO`
  - sucesso -> `ENVIADO` + `NUMERO_RECIBO`
  - erro -> `REJEITADO` com retry/backoff 5/15/45 min.
- DLQ / Poison Pill:
  - configuração `config/esocial.php` com `max_retry`;
  - ao exceder limite: status `FALHA_PERMANENTE`;
  - colunas de auditoria: `DEAD_LETTER_AT`, `DEAD_LETTER_REASON`.
- Scheduler (`Kernel`): execução a cada 5 minutos com `withoutOverlapping`.

## Escopo desta fase

- Não integra endpoint externo real do Serpro nesta entrega; é base operacional para robustez e observabilidade do pipeline.
