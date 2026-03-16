# GENTE v3 — MAPA DE ESTADO REAL
**Varrido em:** 15/03/2026 por Claude (auditor)
**Substitui:** todas as versões anteriores deste documento
**Próxima atualização:** após cada sprint validado

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

### ~~IC-01~~ ✅ RESOLVIDO — Auth movida para fora do isLocal()
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

### IC-06 🔴 — Margem cartão 5% em vez de 10%
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

## STATUS DOS MÓDULOS

| Módulo | Frontend | Backend | Status real |
|--------|----------|---------|-------------|
| Login / Auth | ✅ LoginView.vue | 🔴 preso em isLocal() | 🔴 CAUSA RAIZ |
| CORS / Sessão | — | config/cors.php | 🔴 BLOQUEANTE |
| Progressão Funcional | ✅ | ⚠️ BOM UTF-8 | 🟠 Risco 500 |
| Consignação | ✅ | ✅ (margem 5% a corrigir) | 🟠 Parcial |
| Folha Pagamento | ✅ | ✅ | ✅ Inativo por auth |
| Holerite PDF | ✅ | ⚠️ view Blade faltando | 🟡 Pendente |
| Neoconsig | ❌ | ❌ não existe | 🔴 Sprint 3 |
| Todos os outros módulos | ✅ | ✅ | ✅ Inativos por auth — desbloqueiam com IC-01+02 |

---

*GENTE v3 | RR TECNOL | São Luís — MA | Varrido em 15/03/2026*
