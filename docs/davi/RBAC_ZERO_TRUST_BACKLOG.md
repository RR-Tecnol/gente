# Backlog de arquitectura — RBAC Zero Trust e isolamento por assignment

**Estado:** planeado para **sprint futura** (pós Go-Live imediato).  
**Relação:** complementa a auditoria da Fase 12.5 descrita no plano interno *Fase 12.5 RBAC 360* e a secção correspondente em [`BUSINESS_RULES.md`](BUSINESS_RULES.md).

**Go-Live imediato:** a prova da trava SEMAD em ambiente realista usa o utilizador isolado criado por `AuditorSemadHomologSeeder` com `GENTE_SEED_AUDITOR_SEMAD_STANDALONE=1` (login `auditor@semad.local`, senha `Trocar@123`, **um único** `GENTE_ASSIGNMENT` `auditoria_matriz_semad` + `GLOBAL_SEMAD`). Nesse perfil, a fusão global de slugs coincide com o papel SEMAD — não há “sopa” de dois chapéus.

---

## 1. Diagnóstico (síntese da auditoria 360º)

### 1.1 Segurança — backend

- `RbacResolver::permissionSlugsForUsuario` agrega **todos** os assignments activos do utilizador, sem tenant nem assignment activo na sessão.
- `SpaAuthPayloadBuilder` expõe essa união em `rbac_permission_slugs` no `/api/auth/me`.
- **Risco:** decisões de UI baseadas só nessa lista tratam o utilizador como tendo a **união** de poderes; não reflectem um chapéu único por operação.
- **Inconsistência escala:** `SemadEscalaReadOnly` bloqueia mutações em `escala-trabalho` se existir **qualquer** assignment `auditoria_matriz_semad`, mesmo que outro assignment (ex.: TI SEMED) conceda `escala.grade.editar` — o backend pode ser **mais restritivo** que a UI sugere quando os slugs vêm fundidos.

### 1.2 UX — frontend

- O context switcher actual (`X-Gente-Funcionario-Context-Id`) escolhe **vínculo empregatício** (`FUNCIONARIO_ID`), não **assignment RBAC** (`GENTE_ASSIGNMENT_ID`).
- Não existe header para “chapéu RBAC activo”; o Pinia assume `rbac_permission_slugs` como conjunto único.

### 1.3 Tenant scope (Fase 3C)

- `EnsureTenantScope` está presente no pipeline `api/v3`, mas a política efectiva depende de `GENTE_TENANT_SCOPE_MIDDLEWARE` e `GENTE_TENANT_SCOPE_ENFORCE` em [`config/gente_tenant_rings.php`](../../config/gente_tenant_rings.php).
- Os anéis cobrem prefixos explícitos (ex.: `escala-trabalho`, `escala-saude`, `funcionarios`, …), não “todo o ERP” por omissão.

### 1.4 Workarounds a extinguir após o isolamento

- Campos / getters como `semad_manta_ui_readonly` e lógica associada no Vue corrigem **sintomas** da fusão (chapéu duplo na UI) sem substituir a fonte de verdade por requisição. Após a refacção, devem ser **removidos ou reduzidos** a compatibilidade temporária com testes e deploy em fases.

---

## 2. Arquitectura alvo (refacção definitiva)

### 2.1 Contrato HTTP

- **Header:** `X-Gente-Active-Assignment-Id` (valor = `GENTE_ASSIGNMENT_ID`), nome configurável em `config/gente.php` (análogo a `gente.funcionario_context.header`).
- **Política de obrigatoriedade (produto):** se o utilizador tiver **mais de um** assignment RBAC activo, o header torna-se **obrigatório** em `api/v3`; com um único assignment, o servidor pode derivar o ID automaticamente (menos fricção) ou exigir sempre o header por simetria e auditoria — decisão a fechar na sprint.

### 2.2 Validação server-side

- Middleware após `auth`: resolver o ID, validar `USUARIO_ID`, `ASSIGNMENT_ATIVO`, vigência (`VIGENCIA_INICIO` / `VIGENCIA_FIM`), e rejeitar assignments inexistentes ou de outro utilizador (403).
- Gravar no `Request` atributos estáveis, por exemplo `gente.active_assignment_id`, `gente.active_tenant_type`, `gente.active_tenant_id`, para consumo por `RbacResolver`, policies e serviços.

### 2.3 `RbacResolver`

- Variantes **scoped** à sessão: por exemplo `permissionSlugsForActiveAssignment($usuarioId, $assignmentId)` e `can(...)` restrito ao assignment activo; ou construtor / contexto injectável com `?int $activeAssignmentId` lido do request.
- Manter métodos “unscoped” apenas para rotas internas / batch com deprecação e log em canal `security` (modo shadow) até migração completa.

### 2.4 `SpaAuthPayloadBuilder` (`/api/auth/me`)

- Devolver:
  - **`assignments`:** lista de `{ id (GENTE_ASSIGNMENT_ID), role_slug, tenant_type, tenant_id, label_ui }` para o selector;
  - **`active_assignment_id`:** eco do header ou valor derivado;
  - **`rbac_permission_slugs`:** calculados **apenas** a partir do assignment activo (não a união global).
- Ajustar significados: `semad_auditor_readonly` deve reflectir “assignment activo é papel auditor SEMAD”, quando aplicável; remover `semad_manta_ui_readonly` quando a paridade backend/UI estiver garantida por testes.

### 2.5 Middlewares existentes

- **`SemadEscalaReadOnly`:** avaliar o **assignment activo** (bloquear mutações só se o chapéu activo for SEMAD auditor), em linha com o header — evita bloquear TI quando o utilizador opera explicitamente como SEMED.
- **`TenantScopeEvaluator`:** alinhar regras `SEMAD_READ_ONLY` e `semad_block_mutations` à mesma noção de chapéu activo, evitando duas definições divergentes.

### 2.6 Frontend (Vue 3)

- Pinia: estado `activeAssignmentId`, persistência (ex.: `sessionStorage`), selector na topbar (“Chapéu RBAC” / copy institucional).
- [`axios.js`](../../resources/gente-v3/src/plugins/axios.js): interceptor que envia o header em todas as chamadas `api/v3`.
- Após troca de chapéu: `fetchUser(true)` para recarregar `/me` com slugs coerentes com o assignment seleccionado.

### 2.7 Testes e CI

- Feature: utilizador com dois assignments — com header apontando ao TI, `POST` escala permitido (sujeito a regras de negócio); com header SEMAD, 403.
- Unit: `RbacResolver` scoped vs. legado.
- Manter [`RbacMatrixAuditorHomologacaoTiTest.php`](../../tests/Unit/RbacMatrixAuditorHomologacaoTiTest.php) (anti-drift YAML) independentemente da semântica de assignment.

### 2.8 Documentação e performance

- Actualizar `BUSINESS_RULES.md` com o contrato Zero Trust final (headers, matriz chapéu vs. vínculo empregatício).
- Se existir cache de permissões por utilizador, passar chave a `(usuario_id, assignment_id)` — referenciar em [`PERFORMANCE_BACKLOG.md`](PERFORMANCE_BACKLOG.md) se relevante.

---

## 3. Ordem de implementação sugerida (sprint futura)

1. Middleware de assignment + atributos no `Request`.
2. `RbacResolver` + `SpaAuthPayloadBuilder` (lista de assignments + slugs scoped).
3. `SemadEscalaReadOnly` + `TenantScopeEvaluator` (coerência com chapéu activo).
4. Vue: selector + interceptor Axios.
5. Remover workarounds (`semad_manta_ui_readonly`, etc.) após cobertura de testes.
6. Extensão opcional: novos prefixos em `gente_tenant_rings` ou política de negação por omissão para mutações sensíveis fora dos anéis.

---

## 4. Fora de âmbito deste backlog

- **Fase 13** — Motor de Folha assíncrono (jobs, filas): não depende deste documento para ser priorizado.
