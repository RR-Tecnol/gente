# Runbook — Operação assistida S9 (2026-04-27)

## Objetivo

Garantir estabilização do ciclo S6-S8 com monitoramento diário e resposta rápida a desvios.

## Rotina diária (D+1)

0. Executar preflight técnico: `./scripts/preflight_prontidao.sh`.
1. Executar `php artisan gente:healthcheck --json`.
2. Verificar `esocial_rejeitado` e `esocial_pendente_envio`.
3. Verificar `rpps_bloqueados` e eventos de desbloqueio.
4. Registrar evidências em ata operacional da SEMAD.

## Critérios de alerta

- eSocial rejeitado > 0 por 2 execuções consecutivas.
- RPPS bloqueado com volume acima da média da competência.
- Falha no healthcheck (`status=erro`).

## Ações de contenção

1. Reprocessar fila: `php artisan esocial:processar-fila --limit=80`.
2. Reprocessar prova de vida: `php artisan rpps:prova-vida-processar --inicializar`.
3. Abrir incidente com evidências (comando, horário, erro, impacto).
4. Acionar rollback apenas se houver regressão funcional em produção.

## Gate operacional de go-live

- Comando obrigatório de decisão:
  - `php artisan gente:prontidao-certificar --json`
- Go-live somente se:
  - `status=pass`
  - `go_live_decisao=go`
  - `blockers=[]`

## Encerramento da operação assistida

Encerrar quando houver 10 dias úteis consecutivos sem incidente crítico P1/P2.

## Smoke do pipeline Shadow (P3)

Executar a partir do diretório `gente/`, com banco acessível (`DB_HOST` coerente com host vs Docker; ver `docs/P0_DIAGNOSTICO_SQLSERVER_CONN_2026-04-27.md`).

```bash
FIXTURE="$(pwd)/tests/fixtures/shadow_pilot_minimal/2026-04"
php artisan shadow:snapshot-validar 2026-04 --snapshot-dir="$FIXTURE"
php artisan shadow:dispatch 2026-04 --snapshot-dir="$FIXTURE" --etapa=todas --chunk=50 --run-id=piloto-smoke-$(date +%Y%m%d-%H%M%S)
php artisan shadow:relatorio-run "<RUN_ID>" --json --persistir
```

Critério esperado no smoke da fixture: `status_aceite=aprovado_sem_criticos`, `FALHA_SISTEMICA_CRITICA=0`. O passo **calc** pode registrar `calc_ok` com `erro` se não houver `folha_id`/dados reais em `DETALHE_FOLHA`; o **diff** usa os CSVs do snapshot.

Após fechar o run (relatório), exportar evidências §15.10:
```bash
php artisan shadow:export-run "<RUN_ID>"
```
Para snapshot canónico com `manifest.json` completo (plano §15), antes do dispatch:
```bash
php artisan shadow:snapshot-canonico-validar 2026-04 --snapshot-dir="$FIXTURE"
```
