# GENTE v3 — PLANO MESTRE DE IMPLEMENTAÇÃO
**Versão:** 2.0 | **Vigência:** 15/03/2026
**Projeto:** GENTE — Gestão de Pessoas | Prefeitura Municipal de São Luís / MA
**Empresa:** RR TECNOL
**Stack:** Laravel 10 + Vue 3 + Vite + Vuetify + SQLite (dev) / SQL Server (prod)

> Este documento substitui todos os planos anteriores (PLANO_SPRINTS.md, SPRINTS_GENTE_V3_2.md).
> É a fonte de verdade a partir de 15/03/2026.
> Atualizar após cada sprint concluído.

---

## VISÃO GERAL DO PRODUTO

O GENTE é uma plataforma municipal de gestão de pessoas com dois horizontes:

**Horizonte 1 — PoC (até ~15/04/2026)**
Folha de pagamento funcionando com dados reais da PMSL.
Objetivo: fechar contrato com o município de São Luís.

**Horizonte 2 — Produto Comercial (pós-contrato)**
ERP municipal completo: RH + Tesouraria + Patrimônio + Contabilidade + Orçamento.
Objetivo: expandir para outros municípios.

---

## STATUS ATUAL (15/03/2026)

| Item | Status |
|------|--------|
| Login e autenticação | ✅ Funcionando |
| Dashboard | ✅ Funcionando |
| Funcionários (CRUD) | ✅ Funcionando |
| Folha de pagamento | 🟡 Parcial — validar engine |
| Holerite PDF | 🟡 View existe, validar geração |
| Consignação | 🟡 Margem cartão 5% → corrigir para 10% |
| Progressão Funcional | ✅ Funcionando |
| RPPS/IPAM | ✅ Funcionando |
| Neoconsig | 🔴 Não implementado |
| ERP (6 módulos) | 🟡 Stubs — pós-contrato |
| VPS / Deploy | 🔴 Não contratado |

---

## HORIZONTE 1 — SPRINTS DA POC

### ✅ SPRINT 0 — Login e infraestrutura (CONCLUÍDO 15/03/2026)

**Resultado:** Sistema acessível. Login, navegação e logout funcionando.

| Task | Status | O que foi feito |
|------|--------|----------------|
| IC-01 Auth fora do isLocal() | ✅ | routes/web.php corrigido |
| IC-02 CORS | ✅ | config/cors.php corrigido |
| IC-04 BOM UTF-8 | ✅ | progressao_funcional.php limpo |
| IC-05 APP_URL / SESSION | ✅ | .env corrigido |
| Porta XAMPP | ✅ | Backend em :8080 |
| Proxy Vite /login | ✅ | vite.config.js corrigido |
| Favicon | ✅ | index.html corrigido |
| Bug atribuicao Lotacao | ✅ | web.php historico corrigido |

---

### 🔄 SPRINT 1 — Validação de módulos RH (Semana de 16/03/2026)

**Objetivo:** Garantir que os módulos RH existentes funcionam corretamente.
**Critério de conclusão:** Navegar por todos os módulos sem erro 500.

| Task | Módulo | O que validar |
|------|--------|---------------|
| VAL-01 | Funcionários | CRUD completo, lotação, histórico funcional |
| VAL-02 | Folha de Pagamento | Engine de cálculo, rubricas, competência |
| VAL-03 | Holerite PDF | Geração e download sem erro 500 |
| VAL-04 | Consignação | Fluxo completo, margem separada |
| VAL-05 | Progressão Funcional | Elegibilidade, cálculo de vencimento |
| VAL-06 | Ponto Eletrônico | Apuração mensal |
| VAL-07 | Banco de Horas | Saldo e movimentação |
| VAL-08 | Férias e Licenças | Período aquisitivo, gozo |
| VAL-09 | RPPS/IPAM | Alíquotas, contribuições |
| VAL-10 | Exoneração | Cálculo rescisão + IRRF |

---

### 🔜 SPRINT 2 — Correções e holerite (Semana de 23/03/2026)

**Objetivo:** Corrigir bugs encontrados no Sprint 1 + holerite PDF pronto.

| Task | Prioridade | O que fazer |
|------|-----------|-------------|
| IC-06 Margem cartão 5%→10% | 🔴 | consignacao.php — 2 lugares |
| IC-07 Holerite PDF | 🔴 | Validar/corrigir view holerite-pdf.blade.php |
| Bugs Sprint 1 | 🟠 | Corrigir erros encontrados na validação |

---

### 🔜 SPRINT 3 — Neoconsig (Semana de 30/03/2026)

**Objetivo:** Integração completa com o sistema Neoconsig.
**Base legal:** Decreto Municipal nº 57.477/2021

| Task | O que implementar |
|------|------------------|
| Migration | NEOCONSIG_ID_OPERACAO + NEOCONSIG_VINCULO em CONSIG_CONTRATO |
| TASK-10a | POST /neoconsig/importar-debitos |
| TASK-10b | POST /neoconsig/importar-retorno |
| TASK-10c | GET /neoconsig/gerar-cadastro (523 chars/linha) |
| TASK-10d | GET /neoconsig/gerar-financeiro (66 chars/linha) |
| TASK-10e | GET /neoconsig/gerar-retorno-quitadas |
| TASK-10f | GET /neoconsig/gerar-retorno-pendentes |

---

### 🔜 SPRINT 4 — Dados reais + Deploy (Semana de 06/04/2026)

**Objetivo:** Sistema em produção com dados reais do município.

| Task | O que fazer |
|------|-------------|
| Contratar VPS | Ubuntu 22 + PHP 8.1 + MySQL + Nginx |
| Importar dados reais | Script de importação FUNCIONARIO + FOLHA |
| Deploy | Build + migrations + seed em produção |
| Segurança | APP_DEBUG=false, HTTPS, CORS produção |
| Testes finais | Validar folha com dados reais |

---

### 🔜 SPRINT 5 — PoC (~15/04/2026)

**Objetivo:** Apresentação para a gestão municipal de São Luís.

| Critério de sucesso | |
|--------------------|-|
| Login em < 3s | ✅ a validar |
| Dashboard com dados reais | ✅ a validar |
| Holerite PDF gerado na hora | ✅ a validar |
| Valores corretos vs sistema atual | ✅ a validar |

---

## HORIZONTE 1.5 — APP MOBILE (paralelo aos sprints da PoC)

### Estado atual (15/03/2026)

| Feature | Status |
|---------|--------|
| Login com CPF + JWT | ✅ Completo |
| Home com status do dia | ✅ Completo |
| Bater ponto com câmera frontal | ✅ Completo |
| Reconhecimento facial local (expo-face-detector) | ✅ Fase 1 pronta |
| Geolocalização na batida | ✅ Implementado |
| Histórico de ponto | ✅ Existe |
| Arquitetura para migrar p/ AWS/Azure | ✅ Abstraída em FaceService.js |

### O que falta para a PoC

| Feature | Sprint | Prioridade |
|---------|--------|------------|
| Tela de Holerites | App Sprint 1 | 🔴 Alta |
| Tela de Escala | App Sprint 1 | 🔴 Alta |
| Georreferenciamento no sistema **web** | App Sprint 1 | 🔴 Alta |
| Solicitações (férias, abono) | App Sprint 2 | 🟠 Média |
| Notificações push | App Sprint 2 | 🟠 Média |

### Fora do escopo da PoC

| Feature | Motivo |
|---------|--------|
| Leitura de digital (hardware) | Hardware não definido — deixar arquitetura preparada |
| Migração facial p/ AWS Rekognition | Fase 1 local basta para a PoC |

### App Sprint 1 — Telas da PoC (Semana de 23/03/2026)

**APP-01 — Tela de Holerites**
- Listar holerites por competência
- Visualizar/baixar PDF do contracheque
- Endpoint: GET /ponto/app/holerites

**APP-02 — Tela de Escala**
- Exibir escala do servidor para o mês
- Destacar próximos plantões
- Endpoint: GET /ponto/app/escala

**APP-03 — Georreferenciamento no sistema web**
- Tela de ponto web exibir mapa com localização da batida
- Validar raio configurável por terminal (padrão 50m)
- Admin configura lat/lng de cada unidade
- Endpoint já existe em ponto_app.php — validar funcionamento

### App Sprint 2 — Funcionalidades extras (Semana de 30/03/2026)

**APP-04 — Solicitações**
- Solicitar férias pelo app
- Solicitar abono de falta
- Acompanhar status das solicitações

**APP-05 — Notificações push**
- Usar expo-notifications
- Notificar: aprovação de solicitação, lembrete de ponto, holerite disponível

### Arquitetura de digital (preparar para o futuro, não implementar agora)

Quando o hardware for definido, a integração será feita em `routes/ponto_app.php`
adicionando um endpoint POST /ponto/app/registrar-digital que aceita:
- template_digital (hash da biometria lida pelo leitor)
- tipo (ENTRADA/SAIDA/etc)
- latitude + longitude

Suporte a REP com arquivo AFD será um importador batch separado.

---

## HORIZONTE 2 — ERP MUNICIPAL (pós-contrato)

> Estes módulos têm frontend (Vue) e backend (routes PHP) criados como stubs.
> Implementação começa após assinatura do contrato com São Luís.

### ERP Sprint 1 — Orçamento
- Cadastro de unidades orçamentárias
- Dotações por programa/ação
- Suplementações e reduções
- Relatório LOA/LDO

### ERP Sprint 2 — Execução de Despesa
- Empenho, liquidação, pagamento
- Controle de saldo por dotação
- Integração com tesouraria

### ERP Sprint 3 — Contabilidade (PCASP)
- Plano de contas PCASP
- Lançamentos contábeis
- Balancetes e balanços
- Integração SINC-Folha / TCE-MA

### ERP Sprint 4 — Tesouraria
- Fluxo de caixa diário
- Conciliação bancária
- Ordem de pagamento
- Integração com banco

### ERP Sprint 5 — Patrimônio
- Cadastro de bens
- Depreciação automática
- Inventário anual
- Transferências entre setores

### ERP Sprint 6 — SAGRES / TCE-MA
- Geração de arquivos SAGRES
- Exportação SINC-Folha
- Validação de consistência
- Transmissão automática

---

## ROADMAP VISUAL

```
SEMANA        SISTEMA WEB                    APP MOBILE
─────────────────────────────────────────────────────────────────
15/03  ✔ S0 Login               (app já tem ponto + facial + geo)
16/03  S1 Validação RH          ─
23/03  S2 Correções + PDF       App S1: Holerites + Escala + Geo web
30/03  S3 Neoconsig             App S2: Solicitações + Push
06/04  S4 Dados reais + VPS     Conectar app à VPS
15/04  S5 PoC 🎯               Demo app ao vivo
─────────────────────────────────────────────────────────────────
PÓS-CONTRATO
├─ ERP S1 Orçamento
├─ ERP S2 Execução Despesa
├─ ERP S3 Contabilidade (PCASP)
├─ ERP S4 Tesouraria
├─ ERP S5 Patrimônio
├─ ERP S6 SAGRES/TCE-MA
└─ App: Digital (quando hardware definido)
```

---

*GENTE v3 | Plano Mestre v2.0 | RR TECNOL | 15/03/2026*
*Vigência: 15/03/2026 — substituir ao criar v3.0*
