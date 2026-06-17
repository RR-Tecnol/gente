# S9 — Roteiro de regressão mínima (2026-04-27)

## 1) Gate técnico

1. `composer run check-routes` -> sem colisões METHOD+URI.
2. `php artisan gente:healthcheck --json` -> status `ok`.
3. `php artisan esocial:processar-fila --limit=20` -> sem erro fatal.
4. `php artisan rpps:prova-vida-processar --inicializar` -> execução concluída.

## 2) Gate funcional (smoke)

1. RPPS: `GET /api/v3/rpps/prova-vida` retorna coleção com competência.
2. RPPS: `POST /api/v3/rpps/prova-vida/inicializar` idempotente (segunda execução não duplica).
3. eSocial: gerar evento e enfileirar `POST /api/v3/esocial/eventos/{id}/enfileirar`.
4. Transparência: `GET /api/v3/transparencia/dossie-terceirizacao` sem CPF integral.
5. Transparência: `GET /api/v3/transparencia/observabilidade-integracoes` com métricas agregadas.

## 3) Gate documental

- Atualizar plano principal (sec. 10 e 12).
- Publicar nota da sprint S9 com evidências.
- Sincronizar espelho Obsidian.
