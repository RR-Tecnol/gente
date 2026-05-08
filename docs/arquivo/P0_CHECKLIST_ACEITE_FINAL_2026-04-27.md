# P0 — Checklist de aceite final (2026-04-27)

## Governança

- [ ] Matriz RACI aprovada pelo comitê.
- [ ] Catálogo SLO/SLA/KRI aprovado e com limiares definidos.
- [ ] Política de incidentes P1/P2/P3 validada.

## Operação

- [ ] Runbook operacional publicado e acessível ao time.
- [ ] Contatos de plantão definidos por papel.
- [ ] Canal oficial de alerta configurado e testado.
- [ ] `scripts/preflight_prontidao.sh` executado e arquivado como evidência.

## Evidência

- [ ] Documento de go/no-go assinado por gestão técnica e negócio.
- [ ] Trilhas de decisão e logs de validação arquivados.
- [ ] Testes de isolamento de contexto tenant (subdomínio/header e não-vazamento) executados com sucesso.
- [ ] `gente:prontidao-certificar --json` retornando `status=pass` no ambiente de homologação alvo.
- [ ] `gente:prontidao-certificar --json` com `go_live_decisao=go` e sem `blockers`.
