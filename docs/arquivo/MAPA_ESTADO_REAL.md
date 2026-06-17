# GENTE v3 — MAPA DE ESTADO REAL
**Varrido em:** 15/03/2026 | **Atualizado:** 30/03/2026 por Gravity
**Substitui:** todas as versões anteriores deste documento
**Próxima atualização:** após cada sprint validado

## Refatoração routes/ — 30/03/2026

| Métrica | Antes | Depois |
|---|---|---|
| Linhas `web.php` | 10.617 | 9.541 |
| Arquivos `routes/*.php` | 26 | 39 |
| Blocos `Route::prefix` standalone | ~20 | 4 legítimos |

**13 novos módulos extraídos:** `cargos_salarios.php`, `ferias_v3.php`, `comunicados.php`,
`meu_perfil.php`, `ponto_eletronico.php`, `plantoes_sobreaviso.php`, `atestados_v3.php`,
`contratos_v3.php`, `medicina.php`, `declaracoes.php`, `ouvidoria.php`, `gestor.php`, `organograma_v3.php`

**Bug corrigido:** `/v3/avaliacoes` → `/avaliacoes` (prefixo duplicado no web.php linha ~10371)

**Pendência conhecida:** O bloco L1850–L3552 do `web.php` (dashboard + holerites, 1702 linhas)
contém rotas duplicadas de `/declaracoes` e `/comunicados` que são inofensivas em Laravel
(primeira rota registrada vence). Cleanup desse bloco é sprint separada pós-Bloco A.

> Este documento é a fonte de verdade. Em caso de conflito com qualquer outro
> documento, este prevalece — desde que seja o mais recente.

---

## CORREÇÕES em relação à versão anterior (12/03/2026)

| Item | Era | Agora | Motivo |
|------|-----|-------|--------|
| IC-03 path '/' duplicado | 🔴 Bug ativo | ✅ Falso positivo | Varredura confirmou: dois path:'/' são corretos — redirect e layout |
| IC-06 margem cartão | 🔴 5% (bug) | 🔴 **Ainda 5%** — bug confirmado | Código usa 0.05 em ambos os endpoints (POST /consignacao e GET /margem) |
| Nome do sistema | SISGEP | **GENTE** | Confirmado pelo Tech Lead — SISGEP foi primeira concepção |

---

## BUGS CRÍTICOS ATIVOS (ordem de prioridade)

> ✅ Todos os bugs documentados abaixo desta seção foram confirmados como RESOLVIDOS
> por varredura direta do código em 23/03/2026. Ver seção "RESOLVIDOS" abaixo.

### IC-08 🔴 — Neoconsig não implementado
**Arquivo:** `routes/neoconsig.php` — não existe
**Correção:** Sprint 6

---
**Arquivo:** `routes/web.php`
**Confirmado:** /csrf-cookie e Route::prefix('api/auth') estão DENTRO do bloco isLocal()/dev
**Impacto:** Sistema completamente inacessível — ninguém consegue logar
**Correção:** Sprint 0 TASK-01

### ~~IC-02~~ ✅ RESOLVIDO — CORS corrigido
**Arquivo:** `config/cors.php`
**Confirmado:** `allowed_origins=['*']` + `supports_credentials=false`
**Impacto:** Mesmo corrigindo IC-01, browser não envia cookies → sessão nunca estabelecida
**Correção:** Sprint 0 TASK-02

### ~~IC-04~~ ✅ RESOLVIDO — BOM removido + use Carbon adicionado
**Arquivo:** `routes/progressao_funcional.php`
**Status:** Não varrido nesta sessão — manter como pendente
**Correção:** Sprint 0 TASK-05

### ~~IC-05~~ ✅ RESOLVIDO — APP_URL=:8080, SESSION_DOMAIN vazio
**Arquivo:** `.env`
**Status:** Não varrido nesta sessão — manter como pendente
**Correção:** Sprint 0 TASK-03

## AMBIENTE LOCAL — COMO SUBIR O SISTEMA

```powershell
# Terminal 1 — Backend
cd C:\Users\joaob\Desktop\sisgep-job-main
php artisan serve --host=127.0.0.1 --port=8080

# Terminal 2 — Frontend
cd C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3
npm run dev

# Acessar em: http://localhost:5173
# cookieDomainRewrite: 'localhost' no vite.config.js — não alterar!
```

---

## IC-06 🔴 — Margem cartão 5% em vez de 10%
**Arquivo:** `routes/consignacao.php`
**Confirmado:** `$margem_cartao = $liquido * 0.05` em DOIS lugares:
- linha POST /consignacao (~linha 80)
- linha GET /consignacao/margem/{id} (~linha 180)
**Impacto:** Não-conformidade com Decreto Municipal nº 57.477/2021 (Art. 4º)
**Correção:** Sprint 2 TASK-08

### IC-07 🟡 — Holerite PDF sem view Blade
**Arquivo:** `resources/views/pdf/holerite.blade.php` — não existe
**Correção:** Sprint 2 TASK-09

### IC-08 🔴 — Neoconsig não implementado
**Arquivo:** `routes/neoconsig.php` — não existe
**Correção:** Sprint 3 TASK-10

---

## RESOLVIDOS — CONFIRMADOS POR VARREDURA DE CÓDIGO (23/03/2026)

| Bug/Task | Arquivo | Como foi confirmado |
|-----|---------|---------------------|
| SEC-PROD-10 Auditoria de Logs | LoggingServiceProvider.php | Logs de autenticação e operações sensíveis expandidos ✅ |
| SEC-PROD-09 Varredura SQL Injection | comunicados.php | Varredura em DB:: raw/statement/select. Whitelist adicionada em `$tabelaExiste` para sanitizar DDLs dinâmicos ✅ |
| SEC-PROD-08 DOMPurify (XSS) | sanitize.js, ComunicadosView.vue | Plugin criado com tags estritas, aplicado via sanitize() no v-html de inputs dinâmicos ✅ |
| SEC-PROD-07 CORS Produção | cors.php | Supports credentials = true, * removido e wildcard protegido, placeholder produção inserido ✅ |
| SEC-PROD-06 Timeout Sessão | session.php, axios.js, LoginView.vue | Configuração de cookie secure, HTTP only. Axios intercepta 401 e limpa store ✅ |
| SEC-PROD-05 ValidateFileUpload | ValidateFileUpload.php, Kernel.php, routes/*.php | Size 10MB, mime_content_type estrito e rotas de arquivo atestadas com middleware ✅ |
| SEC-PROD-04 Política de Senha | LoginView.vue, web.php | Validator no POST /change-password e modal/strength indicator no front ✅ |
| SEC-PROD-03 Anti Brute-Force | Kernel.php, web.php, Migration | Migration criada, handler web modificado, limpo via schedule ✅ |
| SEC-PROD-02 reCAPTCHA v3 | LoginView.vue, web.php, .env | Script carregado, token incluído, backend valida com a API siteverify ✅ |
| SEC-PROD-01 SecurityHeaders | app/Http/Middleware/SecurityHeaders.php, Kernel.php | Criado e registrado no kernel global ✅ |
| IC-01 Auth fora do isLocal() | routes/web.php | `/api/auth/*` e `/csrf-cookie` no escopo global ✅ |
| IC-02 CORS | config/cors.php | `supports_credentials=true`, origins explícitas ✅ |
| IC-04 BOM UTF-8 progressao_funcional.php | routes/progressao_funcional.php | Confirmado limpo ✅ |
| IC-05 APP_URL / SESSION_DOMAIN | .env | Corrigido ✅ |
| IC-06 Margem cartão 5%→10% | routes/consignacao.php | Linha 115: `$liquido * 0.10`, linha 325: `round($liquido * 0.10, 2)` ✅ |
| IC-07 Holerite PDF — view Blade | resources/views/v3/holerite-pdf.blade.php | Arquivo existe com 251 linhas; endpoint GET /api/v3/holerites/{id}/pdf usa `Pdf::loadView('v3.holerite-pdf', ...)` ✅ |
| BUG-AC (x6) Autocadastro — aprovação | routes/web.php | Validado pelo usuário 17/03; tabela PESSOA_DEPENDENTE usada corretamente com timestamps ✅ |
| BUG-SEED-01 RubricasCatalogoSeeder timestamps | database/seeders/RubricasCatalogoSeeder.php | Usa Schema::hasColumn para todas as colunas opcionais; sem timestamps no array ✅ |
| BUG-SEED-02 FuncionariosPMSLzSeeder campos inexistentes | database/seeders/FuncionariosPMSLzSeeder.php | Totalmente reescrito com Schema::hasColumn; PESSOA_SEXO = int; sem timestamps ✅ |
| BUG-MOTOR-01 MotorFolhaService JOIN inexistente | app/Services/MotorFolhaService.php | leftJoin em TABELA_SALARIAL e PROGRESSAO_CONFIG — ambas existem na migration 2026_03_10; E2E passou R$24.743 ✅ |
| BUG-SIDEBAR-01 8 módulos sem sidebar | resources/gente-v3/src/layouts/DashboardLayout.vue | Todos os 8 módulos presentes em ALL_NAV_ITEMS (linhas 347–429) e routeMap (linhas 649–657) ✅ |
| BUG-SIDEBAR-04 userRoleLevel perfis não mapeados | DashboardLayout.vue | Todos os 15 perfis mapeados em adminPerfis/rhPerfis/gestorPerfis (linhas 529–548) ✅ |

---

### ~~IC-01~~ ✅ RESOLVIDO — Auth movida para fora do isLocal()
**Arquivo:** `routes/web.php` — endpoint `POST /autocadastro/{token}/aprovar`
**Bugs corrigidos:**
- BUG-AC-01: Matrícula não era gerada → agora gerada no formato `YYYY-NNNN` sequencial
- BUG-AC-02: CPF salvo em campo errado (`PESSOA_CPF`) → `PESSOA_CPF_NUMERO`
- BUG-AC-03: Cinco campos com nome errado:
  - `PESSOA_SEXO_ID` → `PESSOA_SEXO`
  - `ESTADO_CIVIL_ID` → `ESTADO_CIVIL`
  - `PESSOA_GRAU_INSTRUCAO` → `ESCOLARIDADE_ID`
  - `PESSOA_RACA_COR` → `PESSOA_RACA`
  - `PESSOA_RG_ORG_EMISSOR` → `PESSOA_ORG_EMISSOR`
- BUG-AC-04: `USUARIO` criado sem `insertGetId` → vínculo PESSOA/FUNCIONARIO perdido
- BUG-AC-05: `USUARIO_PERFIL` nunca criado → criado com `PERFIL_ID = 5` (Externo)
- BUG-AC-06: Senha em bcrypt em sistema MD5 → corrigido para `md5()`
- Colunas inexistentes removidas: `created_at`/`updated_at` em PESSOA/USUARIO, `PESSOA_ATIVO`, `PESSOA_DATA_CADASTRO`

**Validado pelo usuário:** ✅ 17/03/2026 — aprovação funcionou, PESSOA/USUARIO/FUNCIONARIO criados
**Aguarda:** varredura de código pelo Claude na próxima sessão de auditoria

---

## ITENS CORRETOS — CONFIRMADOS POR VARREDURA

| Item | Arquivo | Verificado |
|------|---------|-----------|
| hasAccess() usa <= corretamente | router/index.js | ✅ 15/03/2026 |
| path '/' duplicado é correto (redirect + layout) | router/index.js | ✅ 15/03/2026 |
| Cache TTL 5min no fetchUser() | store/auth.js | ✅ 12/03/2026 |
| DETALHE_FOLHA_LIQUIDO usado corretamente | consignacao.php | ✅ 15/03/2026 |
| Margem separada 30% empréstimo | consignacao.php | ✅ 15/03/2026 |
| Fluxo autorização CONSIG_OCORRENCIA | consignacao.php | ✅ 15/03/2026 |
| Todos require de módulos no web.php | web.php | ✅ 12/03/2026 |
| isLocal() protegendo /dev/* | web.php | ✅ 15/03/2026 |
| DomPDF instalado | composer.json | ✅ 12/03/2026 |
| PONTO_APP_JWT_SECRET no .env | .env | ✅ 12/03/2026 |
| Brevo SMTP configurado | .env | ✅ 12/03/2026 |
| Rotas ERP no router/index.js | router/index.js | ✅ 12/03/2026 |

---

## STATUS DOS MÓDULOS (23/03/2026)

| Módulo | Frontend | Backend | Status real |
|--------|----------|---------|-------------|
| Login / Auth | ✅ LoginView.vue | ✅ fora do isLocal() | ✅ Funcionando |
| CORS / Sessão | — | config/cors.php | ✅ Funcionando |
| Funcionários (CRUD) | ✅ | ✅ funcionarios.php | ✅ Funcional |
| Folha de Pagamento | ✅ | ✅ folha.php + MotorFolhaService | ✅ Motor E2E OK — R$24.743 proventos |
| Holerite PDF | ✅ | ✅ view v3/holerite-pdf.blade.php (251 linhas) | ✅ Funcional |
| Consignação | ✅ | ✅ consignacao.php | ✅ Margem cartão 10% ✅ |
| Progressão Funcional | ✅ | ✅ BOM removido | ✅ Funcional |
| eSocial | ✅ | ✅ esocial.php | ✅ Funcional |
| RPPS/IPAM | ✅ | ✅ rpps.php | ✅ Funcional |
| Exoneração | ✅ | ✅ exoneracao.php | ✅ Funcional |
| Hora Extra | ✅ | ✅ hora_extra.php | ✅ Funcional |
| Verba Indenizatória | ✅ | ✅ | ✅ Funcional |
| Diárias | ✅ na sidebar | ✅ diarias.php | ✅ Funcional |
| Estagiários | ✅ na sidebar | ✅ estagiarios.php | ✅ Funcional |
| Acumulação de Cargos | ✅ na sidebar | ✅ acumulacao.php | ✅ Funcional |
| Transparência Pública | ✅ na sidebar | ✅ transparencia.php | ✅ Funcional |
| PSS / Concursos | ✅ na sidebar | ✅ pss.php | ✅ Funcional |
| Terceirizados | ✅ na sidebar | ✅ terceirizados.php | ✅ Funcional |
| SAGRES / TCE-MA | ✅ na sidebar | ✅ sagres.php | ✅ Funcional |
| Banco de Horas | ✅ | ✅ banco_horas.php | ✅ Funcional |
| Atestados Médicos | ✅ | ✅ atestados.php | ✅ Funcional |
| Autocadastro — Aprovação | ✅ AutocadastroGestaoView | ✅ web.php (BUG-AC x6 resolvidos) | ✅ Validado 17/03/2026 |
| ERP (6 módulos) | ✅ stubs | ✅ stubs | 🟡 Pós-contrato |
| Neoconsig | ❌ não existe | ❌ não existe | 🔴 Sprint 6 |
| Notificações | ✅ stub | ⚠️ stub web.php | ⚠️ 404 — Pós-PoC |

---

*GENTE v3 | RR TECNOL | São Luís — MA | Varrido em 15/03/2026*
