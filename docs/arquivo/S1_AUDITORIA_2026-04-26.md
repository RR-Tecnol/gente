# Auditoria de mutações (S1.2) — 2026-04-26

## Estado

- Middleware `AuditLog` (`App\Http\Middleware\AuditLog`) registrado no Kernel como `audit`.
- Grupos `Route::prefix('api/v3')->middleware(['web', 'auth', 'audit'])` em `routes/web.php` (e ramificações) aplicam trilha a **POST/PUT/PATCH/DELETE** autenticados (ver regra no ficheiro do middleware).

## Trabalho futuro (aceite reforçado S1.2)

- [ ] Garantir que **toda** rota de mutação crítica (folha, admissão, V2/V3) passa num grupo com `audit` ou anotação explícita.
- [ ] Amostra em homolog: executar 1 operação e verificar saída em `storage/logs` / canal `security` conforme `AuditLog`.

---

**Aceite mínimo atual:** padrão `api/v3` + `audit` alinhado ao desenho SEC-04; validação de cobertura por módulo fica aberta com `S1_INVENTARIO_RBAC_2026-04-26.md`.
