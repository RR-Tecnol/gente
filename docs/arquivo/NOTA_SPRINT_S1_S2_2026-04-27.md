# Nota de sprint — S1 mín. técnico + S2 (2026-04-27)

## S1 (fecho mínimo)

- **S1.4:** Refatoração: `pesquisa`, `parametros_financeiros_v3`, `cnab`, `treinamentos`, `seguranca_trabalho`, `beneficios`, `medicina`, `medicina_admin` — DDL deslocado para funções `ensure*FromRoutes` + chamada nos handlers. `feriados_v3::ensureCalendarOverridesTable` passa a exigir `migrate` (tabela canónica em migration).
- **S1.2 / S1.3:** sem alteração de código; documentos já em `S1_AUDITORIA` e `S1_HIGIENE` permanecem válidos.
- **S1.1 (pendência consciente):** matriz `perfil` por rota ainda a expandir; ver `S1_INVENTARIO_RBAC_2026-04-26.md`.

## S2

- Gate `composer run check-routes` validado (1061 rotas, 0 colisões).
- Política de wiring: `ROUTE_WIRING_POLICY.md`.

## Próximo alvo (plano)

- **S3** — regras de jornada/frequência/folha (V1), conforme secções 5 do `PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26.md` e `BRAIN_REGRAS_SEMAD_SLZ_JORNADA_2026-04-26.md`.
